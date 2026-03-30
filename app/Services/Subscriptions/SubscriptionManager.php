<?php

namespace App\Services\Subscriptions;

use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class SubscriptionManager
{
    private const STATUS_PRIORITY = "
        case
            when status in ('authorized', 'active') then 0
            when status = 'pending' then 1
            when status = 'paused' then 2
            when status = 'canceled' then 3
            else 4
        end
    ";

    private const OPEN_STATUSES = ['pending', 'authorized', 'active', 'paused'];

    public function __construct(
        private readonly SubscriptionCatalog $catalog,
        private readonly MercadoPagoSubscriptionGateway $mercadoPago,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function plans(): array
    {
        return $this->catalog->all();
    }

    public function currentForUser(User $user, bool $sync = false): ?UserSubscription
    {
        $subscription = $this->currentSubscriptionQuery($user)->first();

        if (
            $sync
            && in_array($subscription->status, self::OPEN_STATUSES, true)
        ) {
            if ($subscription->mercado_pago_preapproval_id) {
                $subscription = $this->syncByMercadoPagoId($subscription->mercado_pago_preapproval_id) ?? $subscription->fresh();
            } elseif (
                $subscription->mercado_pago_payment_id
                && in_array((string) $subscription->latest_payment_status, ['pending', 'in_process'], true)
            ) {
                $subscription = $this->syncPayment($subscription->mercado_pago_payment_id) ?? $subscription->fresh();
            }
        }

        return $subscription;
    }

    public function createCheckout(
        User $user,
        string $planCode,
        ?string $payerEmail = null,
        ?string $cardTokenId = null,
        ?string $deviceSessionId = null,
    ): UserSubscription
    {
        Log::info('3. Entrou em SubscriptionManager@createCheckout', [
            'user_id' => $user->id,
            'plan' => $planCode,
        ]);

        $plan = $this->catalog->get($planCode);
        if (!$plan) {
            Log::warning('4. Plano invalido em SubscriptionManager@createCheckout', [
                'user_id' => $user->id,
                'plan' => $planCode,
            ]);

            throw ValidationException::withMessages([
                'plan' => ['Plano de assinatura inválido.'],
            ]);
        }

        $current = $this->currentSubscriptionQuery($user)->first();
        if ($current && in_array($current->status, self::OPEN_STATUSES, true)) {
            Log::warning('4. Usuario ja possui assinatura em aberto em SubscriptionManager@createCheckout', [
                'user_id' => $user->id,
                'current_subscription_id' => $current->id,
                'current_status' => $current->status,
            ]);

            throw ValidationException::withMessages([
                'plan' => ['Já existe uma assinatura em andamento para este usuário.'],
            ]);
        }

        $checkoutMode = strtolower(trim((string) ($plan['checkout_mode'] ?? 'subscription_pending')));
        $externalReference = $this->generateExternalReference($user, $planCode);

        Log::info('4. Validacoes de negocio concluidas em SubscriptionManager@createCheckout', [
            'user_id' => $user->id,
            'plan' => $planCode,
            'checkout_mode' => $checkoutMode,
            'external_reference' => $externalReference,
        ]);

        $payload = match ($checkoutMode) {
            'subscription_authorized' => $this->mercadoPago->createAuthorizedPreapprovalWithPlan(
                $plan,
                $externalReference,
                $this->resolveAuthorizedPayerEmail($user, $payerEmail),
                $this->requireCardToken($cardTokenId),
                $this->normalizeOptionalDeviceSessionId($deviceSessionId),
            ),
            'subscription_pending' => $this->mercadoPago->createPendingPreapproval(
                $plan,
                $externalReference,
                $this->requirePendingPayerEmail($payerEmail),
            ),
            default => throw new RuntimeException('Unsupported subscription checkout mode: ' . $checkoutMode),
        };

        Log::info('6. Payload recebido do gateway em SubscriptionManager@createCheckout', [
            'user_id' => $user->id,
            'status' => Arr::get($payload, 'status'),
            'preapproval_id' => Arr::get($payload, 'id'),
        ]);

        $subscription = DB::transaction(function () use ($user, $plan, $externalReference): UserSubscription {
            return UserSubscription::query()->create([
                'user_id' => $user->id,
                'plan_code' => (string) $plan['code'],
                'status' => 'pending',
                'external_reference' => $externalReference,
                'transaction_amount' => (float) $plan['amount'],
                'currency_id' => (string) $plan['currency_id'],
                'frequency' => (int) $plan['frequency'],
                'frequency_type' => (string) $plan['frequency_type'],
            ]);
        });

        Log::info('7. Assinatura salva localmente em SubscriptionManager@createCheckout', [
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
        ]);

        return $this->applySubscriptionPayload($subscription, $payload);
    }

    private function resolveAuthorizedPayerEmail(User $user, ?string $payerEmail = null): string
    {
        $resolved = trim((string) ($payerEmail ?? $user->email));
        Log::info('5. Validando payer_email em SubscriptionManager@resolveAuthorizedPayerEmail', [
            'user_id' => $user->id,
        ]);

        if ($resolved === '') {
            Log::warning('5. Falha ao resolver payer_email em SubscriptionManager@resolveAuthorizedPayerEmail', [
                'user_id' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'email' => ['O usuário precisa ter um e-mail válido para assinar.'],
            ]);
        }

        return $resolved;
    }

    private function requirePendingPayerEmail(?string $payerEmail): string
    {
        $resolved = trim((string) $payerEmail);
        Log::info('5. Validando payer_email em SubscriptionManager@requirePendingPayerEmail');

        if ($resolved === '') {
            Log::warning('5. Falha na validacao do payer_email em SubscriptionManager@requirePendingPayerEmail');

            throw ValidationException::withMessages([
                'payer_email' => ['Informe o e-mail da conta do Mercado Pago usada no pagamento.'],
            ]);
        }

        return $resolved;
    }

    private function requireCardToken(?string $cardTokenId): string
    {
        $resolved = trim((string) $cardTokenId);
        Log::info('5. Validando card_token_id em SubscriptionManager@requireCardToken');

        if ($resolved === '') {
            Log::warning('5. Falha na validacao do card_token_id em SubscriptionManager@requireCardToken');

            throw ValidationException::withMessages([
                'card_token_id' => ['Informe o token do cartão gerado pelo checkout do Mercado Pago.'],
            ]);
        }

        return $resolved;
    }

    private function normalizeOptionalDeviceSessionId(?string $deviceSessionId): ?string
    {
        $resolved = trim((string) $deviceSessionId);

        return $resolved !== '' ? $resolved : null;
    }

    public function cancelCurrent(User $user): UserSubscription
    {
        Log::info('3. Entrou em SubscriptionManager@cancelCurrent', [
            'user_id' => $user->id,
        ]);

        $subscription = $this->currentSubscriptionQuery($user)->first();

        if (!$subscription || !in_array($subscription->status, self::OPEN_STATUSES, true)) {
            Log::warning('4. Nenhuma assinatura cancelavel encontrada em SubscriptionManager@cancelCurrent', [
                'user_id' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'subscription' => ['Nenhuma assinatura ativa foi encontrada para cancelamento.'],
            ]);
        }

        if ($subscription->status === 'pending') {
            Log::info('4. Assinatura pendente encontrada em SubscriptionManager@cancelCurrent', [
                'subscription_id' => $subscription->id,
            ]);

            if ($subscription->mercado_pago_payment_id && $this->canCancelStandalonePayment($subscription)) {
                Log::info('5. Cancelando pagamento standalone em SubscriptionManager@cancelCurrent', [
                    'subscription_id' => $subscription->id,
                    'payment_id' => $subscription->mercado_pago_payment_id,
                ]);

                $payload = $this->mercadoPago->cancelPayment($subscription->mercado_pago_payment_id);

                return $this->applyPaymentPayload(
                    $subscription,
                    $payload,
                    $subscription->mercado_pago_payment_id,
                );
            }

            return $this->cancelLocalPendingCheckout($subscription);
        }

        if ($subscription->mercado_pago_preapproval_id) {
            Log::info('5. Cancelando preapproval em SubscriptionManager@cancelCurrent', [
                'subscription_id' => $subscription->id,
                'preapproval_id' => $subscription->mercado_pago_preapproval_id,
            ]);

            $payload = $this->mercadoPago->cancelPreapproval($subscription->mercado_pago_preapproval_id);

            return $this->applySubscriptionPayload($subscription, $payload);
        }

        throw ValidationException::withMessages([
            'subscription' => ['Nenhuma assinatura pendente foi encontrada para cancelamento.'],
        ]);
    }

    public function handleWebhook(Request $request): void
    {
        Log::info('2. Entrou em SubscriptionManager@handleWebhook');

        if ($this->mercadoPago->hasWebhookSecret() && !$this->mercadoPago->isValidWebhookSignature($request)) {
            Log::warning('3. Assinatura do webhook invalida em SubscriptionManager@handleWebhook');

            abort(401, 'Invalid Mercado Pago signature.');
        }

        $type = strtolower((string) ($request->input('type') ?? $request->input('topic') ?? ''));
        $resourceId = (string) ($request->query('data.id') ?? data_get($request->all(), 'data.id', ''));

        match ($type) {
            'subscription_preapproval' => $resourceId !== '' ? $this->syncByMercadoPagoId($resourceId) : null,
            'subscription_authorized_payment' => $resourceId !== '' ? $this->syncAuthorizedPayment($resourceId) : null,
            'payment' => $resourceId !== '' ? $this->syncPayment($resourceId) : null,
            default => Log::info('Mercado Pago webhook ignored.', [
                'type' => $type,
                'resource_id' => $resourceId,
            ]),
        };
    }

    public function syncByMercadoPagoId(string $preapprovalId): ?UserSubscription
    {
        Log::info('4. Entrou em SubscriptionManager@syncByMercadoPagoId', [
            'preapproval_id' => $preapprovalId,
        ]);

        $payload = $this->mercadoPago->fetchPreapproval($preapprovalId);
        $subscription = $this->resolveSubscriptionFromPreapprovalPayload($payload);

        if (!$subscription) {
            Log::warning('Mercado Pago subscription payload could not be matched locally.', [
                'preapproval_id' => $preapprovalId,
                'external_reference' => Arr::get($payload, 'external_reference'),
            ]);

            return null;
        }

        if ($this->shouldIgnoreRemotePreapprovalUpdate($subscription)) {
            Log::info('Mercado Pago preapproval update ignored for locally canceled pending checkout.', [
                'subscription_id' => $subscription->id,
                'preapproval_id' => $preapprovalId,
                'external_reference' => $subscription->external_reference,
            ]);

            return $subscription;
        }

        return $this->applySubscriptionPayload($subscription, $payload);
    }

    public function syncPayment(string $paymentId): ?UserSubscription
    {
        Log::info('4. Entrou em SubscriptionManager@syncPayment', [
            'payment_id' => $paymentId,
        ]);

        $payload = $this->mercadoPago->fetchPayment($paymentId);
        $subscription = UserSubscription::query()
            ->where('external_reference', (string) Arr::get($payload, 'external_reference'))
            ->first();

        if (!$subscription) {
            Log::info('Mercado Pago payment ignored because no local subscription was found.', [
                'payment_id' => $paymentId,
                'external_reference' => Arr::get($payload, 'external_reference'),
            ]);

            return null;
        }

        $subscription = $this->applyPaymentPayload($subscription, $payload, $paymentId);

        if ($subscription->mercado_pago_preapproval_id) {
            return $this->syncByMercadoPagoId($subscription->mercado_pago_preapproval_id) ?? $subscription;
        }

        return $subscription;
    }

    public function syncAuthorizedPayment(string $authorizedPaymentId): ?UserSubscription
    {
        Log::info('4. Entrou em SubscriptionManager@syncAuthorizedPayment', [
            'authorized_payment_id' => $authorizedPaymentId,
        ]);

        $payload = $this->mercadoPago->fetchAuthorizedPayment($authorizedPaymentId);
        $preapprovalId = (string) (
            Arr::get($payload, 'preapproval_id')
            ?? Arr::get($payload, 'subscription_id')
            ?? Arr::get($payload, 'preapproval.id')
            ?? ''
        );

        $subscription = null;
        if ($preapprovalId !== '') {
            $subscription = UserSubscription::query()
                ->where('mercado_pago_preapproval_id', $preapprovalId)
                ->first();
        }

        if (!$subscription) {
            $subscription = UserSubscription::query()
                ->where('external_reference', (string) Arr::get($payload, 'external_reference'))
                ->first();
        }

        if (!$subscription) {
            Log::info('Mercado Pago authorized payment ignored because no local subscription was found.', [
                'authorized_payment_id' => $authorizedPaymentId,
                'preapproval_id' => $preapprovalId,
            ]);

            return null;
        }

        $subscription->forceFill([
            'mercado_pago_authorized_payment_id' => (string) (Arr::get($payload, 'id') ?? $authorizedPaymentId),
        ]);
        $subscription->save();

        $subscription = $this->applyPaymentPayload($subscription, $payload);

        if ($preapprovalId !== '') {
            return $this->syncByMercadoPagoId($preapprovalId) ?? $subscription;
        }

        return $subscription;
    }

    private function currentSubscriptionQuery(User $user): HasMany
    {
        return $user->subscriptions()
            ->orderByRaw(self::STATUS_PRIORITY)
            ->latest('created_at');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveSubscriptionFromPreapprovalPayload(array $payload): ?UserSubscription
    {
        $preapprovalId = (string) Arr::get($payload, 'id', '');
        $externalReference = (string) Arr::get($payload, 'external_reference', '');

        if ($preapprovalId === '' && $externalReference === '') {
            return null;
        }

        $subscriptionQuery = UserSubscription::query();

        if ($preapprovalId !== '') {
            $subscriptionQuery->where('mercado_pago_preapproval_id', $preapprovalId);
        }

        if ($externalReference !== '') {
            if ($preapprovalId !== '') {
                $subscriptionQuery->orWhere('external_reference', $externalReference);
            } else {
                $subscriptionQuery->where('external_reference', $externalReference);
            }
        }

        $subscription = $subscriptionQuery->first();

        if ($subscription) {
            return $subscription;
        }

        $parsedReference = $this->parseExternalReference($externalReference);
        if (!$parsedReference) {
            return null;
        }

        $user = User::query()->find($parsedReference['user_id']);
        $plan = $this->catalog->get($parsedReference['plan_code']);

        if (!$user || !$plan) {
            return null;
        }

        return UserSubscription::query()->create([
            'user_id' => $user->id,
            'plan_code' => (string) $plan['code'],
            'status' => (string) Arr::get($payload, 'status', 'pending'),
            'external_reference' => $externalReference,
            'mercado_pago_preapproval_id' => $preapprovalId !== '' ? $preapprovalId : null,
            'transaction_amount' => (float) (Arr::get($payload, 'auto_recurring.transaction_amount') ?? $plan['amount']),
            'currency_id' => (string) (Arr::get($payload, 'auto_recurring.currency_id') ?? $plan['currency_id']),
            'frequency' => (int) (Arr::get($payload, 'auto_recurring.frequency') ?? $plan['frequency']),
            'frequency_type' => (string) (Arr::get($payload, 'auto_recurring.frequency_type') ?? $plan['frequency_type']),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applySubscriptionPayload(UserSubscription $subscription, array $payload): UserSubscription
    {
        Log::info('8. Aplicando payload de assinatura em SubscriptionManager@applySubscriptionPayload', [
            'subscription_id' => $subscription->id,
            'preapproval_id' => Arr::get($payload, 'id'),
        ]);

        $status = (string) Arr::get($payload, 'status', $subscription->status);

        $subscription->forceFill([
            'mercado_pago_preapproval_id' => Arr::get($payload, 'id', $subscription->mercado_pago_preapproval_id),
            'status' => $status,
            'external_reference' => Arr::get($payload, 'external_reference', $subscription->external_reference),
            'checkout_url' => Arr::get($payload, 'init_point', $subscription->checkout_url),
            'sandbox_checkout_url' => Arr::get($payload, 'sandbox_init_point', $subscription->sandbox_checkout_url),
            'transaction_amount' => $this->moneyValue(Arr::get($payload, 'auto_recurring.transaction_amount'), $subscription->transaction_amount),
            'currency_id' => Arr::get($payload, 'auto_recurring.currency_id', $subscription->currency_id),
            'frequency' => $this->integerValue(Arr::get($payload, 'auto_recurring.frequency'), $subscription->frequency),
            'frequency_type' => Arr::get($payload, 'auto_recurring.frequency_type', $subscription->frequency_type),
            'started_at' => $this->resolveStartedAt(
                $subscription->started_at,
                $status,
                Arr::get($payload, 'auto_recurring.start_date'),
                Arr::get($payload, 'date_created'),
            ),
            'next_payment_at' => $this->parseDate(
                Arr::get($payload, 'next_payment_date')
                    ?? Arr::get($payload, 'auto_recurring.next_payment_date')
            ) ?? $subscription->next_payment_at,
            'canceled_at' => in_array($status, ['canceled', 'canceled'], true)
                ? ($this->parseDate(Arr::get($payload, 'last_modified') ?? Arr::get($payload, 'date_modified')) ?? $subscription->canceled_at ?? now())
                : null,
            'last_notified_at' => now(),
            'raw_payload' => $payload,
        ]);

        $subscription->save();

        $this->refreshUserSummary($subscription->user()->firstOrFail());

        Log::info('9. Payload de assinatura aplicado em SubscriptionManager@applySubscriptionPayload', [
            'subscription_id' => $subscription->id,
            'status' => $status,
        ]);

        return $subscription->fresh();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyPaymentPayload(
        UserSubscription $subscription,
        array $payload,
        ?string $fallbackPaymentId = null,
    ): UserSubscription {
        Log::info('8. Aplicando payload de pagamento em SubscriptionManager@applyPaymentPayload', [
            'subscription_id' => $subscription->id,
            'payment_id' => Arr::get($payload, 'id', $fallbackPaymentId),
        ]);

        $paymentStatus = (string) (
            Arr::get($payload, 'payment.status')
            ?? Arr::get($payload, 'status')
            ?? $subscription->latest_payment_status
        );

        $paymentStatusDetail = (string) (
            Arr::get($payload, 'payment.status_detail')
            ?? Arr::get($payload, 'status_detail')
            ?? $subscription->latest_payment_status_detail
        );

        $paymentId = (string) (
            Arr::get($payload, 'payment.id')
            ?? Arr::get($payload, 'payment_id')
            ?? $fallbackPaymentId
            ?? $subscription->mercado_pago_payment_id
        );

        $startedAt = $subscription->started_at
            ?? $this->parseDate(Arr::get($payload, 'payment.date_approved'))
            ?? $this->parseDate(Arr::get($payload, 'date_approved'))
            ?? $this->parseDate(Arr::get($payload, 'payment.date_created'))
            ?? $this->parseDate(Arr::get($payload, 'date_created'));

        $status = $subscription->status;
        $nextPaymentAt = $subscription->next_payment_at;
        $canceledAt = $subscription->canceled_at;

        if (!$subscription->mercado_pago_preapproval_id) {
            [$status, $nextPaymentAt, $canceledAt] = $this->resolveStandalonePaymentState(
                $subscription,
                $paymentStatus,
                $startedAt,
                $payload,
            );
        }

        $subscription->forceFill([
            'status' => $status,
            'mercado_pago_payment_id' => $paymentId !== '' ? $paymentId : $subscription->mercado_pago_payment_id,
            'latest_payment_status' => $paymentStatus !== '' ? $paymentStatus : $subscription->latest_payment_status,
            'latest_payment_status_detail' => $paymentStatusDetail !== '' ? $paymentStatusDetail : $subscription->latest_payment_status_detail,
            'started_at' => $startedAt,
            'next_payment_at' => $nextPaymentAt,
            'canceled_at' => $canceledAt,
            'checkout_url' => $this->checkoutUrlFromPayload($payload, $subscription->checkout_url),
            'last_notified_at' => now(),
            'latest_payment_payload' => $payload,
        ]);

        $subscription->save();

        $this->refreshUserSummary($subscription->user()->firstOrFail());

        Log::info('9. Payload de pagamento aplicado em SubscriptionManager@applyPaymentPayload', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
            'payment_status' => $paymentStatus,
        ]);

        return $subscription->fresh();
    }

    private function refreshUserSummary(User $user): void
    {
        Log::info('8. Atualizando resumo da assinatura do usuario em SubscriptionManager@refreshUserSummary', [
            'user_id' => $user->id,
        ]);

        $current = $this->currentSubscriptionQuery($user)->first();

        if (!$current) {
            $user->forceFill([
                'subscription_provider' => null,
                'subscription_plan' => null,
                'subscription_status' => null,
                'subscription_reference' => null,
                'subscription_started_at' => null,
                'subscription_renews_at' => null,
                'subscription_canceled_at' => null,
            ])->save();

            return;
        }

        $user->forceFill([
            'subscription_provider' => 'mercado_pago',
            'subscription_plan' => $current->plan_code,
            'subscription_status' => $current->status,
            'subscription_reference' => $current->mercado_pago_preapproval_id ?: $current->external_reference,
            'subscription_started_at' => $current->started_at,
            'subscription_renews_at' => $current->next_payment_at,
            'subscription_canceled_at' => $current->canceled_at,
        ])->save();
    }

    /**
     * @return array{user_id:int, plan_code:string}|null
     */
    private function parseExternalReference(string $externalReference): ?array
    {
        if (preg_match('/^dinfy-u-(\d+)-p-([a-z0-9_-]+)-[a-z0-9-]+$/i', $externalReference, $matches)) {
            return [
                'user_id' => (int) $matches[1],
                'plan_code' => strtolower((string) $matches[2]),
            ];
        }

        if (!preg_match('/^dinfy:user:(\d+):plan:([a-z0-9_-]+):/i', $externalReference, $matches)) {
            return null;
        }

        return [
            'user_id' => (int) $matches[1],
            'plan_code' => strtolower((string) $matches[2]),
        ];
    }

    private function generateExternalReference(User $user, string $planCode): string
    {
        return sprintf('dinfy-u-%d-p-%s-%s', $user->id, $planCode, (string) Str::uuid());
    }

    private function moneyValue(mixed $value, mixed $fallback): float
    {
        if ($value === null || $value === '') {
            return round((float) $fallback, 2);
        }

        return round((float) $value, 2);
    }

    private function integerValue(mixed $value, mixed $fallback): int
    {
        if ($value === null || $value === '') {
            return (int) $fallback;
        }

        return (int) $value;
    }

    private function resolveStartedAt(
        mixed $currentValue,
        string $status,
        mixed $primaryDate = null,
        mixed $fallbackDate = null,
    ): ?Carbon {
        $current = $this->parseDate($currentValue);
        if ($current) {
            return $current;
        }

        $resolved = $this->parseDate($primaryDate) ?? $this->parseDate($fallbackDate);
        if ($resolved) {
            return $resolved;
        }

        return in_array($status, ['authorized', 'active'], true) ? now() : null;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0:string,1:?Carbon,2:?Carbon}
     */
    private function resolveStandalonePaymentState(
        UserSubscription $subscription,
        string $paymentStatus,
        ?Carbon $startedAt,
        array $payload,
    ): array {
        $normalizedStatus = strtolower(trim($paymentStatus));
        $canceledAt = $subscription->canceled_at;

        return match ($normalizedStatus) {
            'approved', 'accredited' => [
                'active',
                $this->calculateStandaloneRenewalAt($subscription, $startedAt) ?? $subscription->next_payment_at,
                null,
            ],
            'pending', 'in_process' => [
                'pending',
                $subscription->next_payment_at,
                $subscription->canceled_at,
            ],
            'canceled', 'canceled', 'rejected', 'refunded', 'charged_back' => [
                'canceled',
                null,
                $this->parseDate(Arr::get($payload, 'date_last_updated') ?? Arr::get($payload, 'date_created'))
                    ?? $canceledAt
                    ?? now(),
            ],
            default => [
                $subscription->status,
                $subscription->next_payment_at,
                $subscription->canceled_at,
            ],
        };
    }

    private function calculateStandaloneRenewalAt(
        UserSubscription $subscription,
        ?Carbon $startedAt,
    ): ?Carbon {
        if (!$startedAt || $subscription->frequency <= 0) {
            return null;
        }

        return match (strtolower($subscription->frequency_type)) {
            'days' => $startedAt->copy()->addDays($subscription->frequency),
            'weeks' => $startedAt->copy()->addWeeks($subscription->frequency),
            'months' => $startedAt->copy()->addMonthsNoOverflow($subscription->frequency),
            'years' => $startedAt->copy()->addYears($subscription->frequency),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function checkoutUrlFromPayload(array $payload, ?string $fallback = null): ?string
    {
        $resolved = Arr::get($payload, 'init_point')
            ?? Arr::get($payload, 'point_of_interaction.transaction_data.ticket_url')
            ?? Arr::get($payload, 'transaction_details.external_resource_url')
            ?? $fallback;

        if (!is_string($resolved)) {
            return $fallback;
        }

        $resolved = trim($resolved);

        return $resolved !== '' ? $resolved : $fallback;
    }

    private function canCancelStandalonePayment(UserSubscription $subscription): bool
    {
        return in_array(
            strtolower((string) $subscription->latest_payment_status),
            ['pending', 'in_process', 'authorized'],
            true,
        );
    }

    private function shouldIgnoreRemotePreapprovalUpdate(UserSubscription $subscription): bool
    {
        return $subscription->status === 'canceled'
            && $subscription->mercado_pago_preapproval_id !== null
            && $subscription->started_at === null
            && $subscription->latest_payment_status === null;
    }

    private function cancelLocalPendingCheckout(UserSubscription $subscription): UserSubscription
    {
        Log::info('8. Cancelando assinatura pendente local em SubscriptionManager@cancelLocalPendingCheckout', [
            'subscription_id' => $subscription->id,
        ]);

        $subscription->forceFill([
            'status' => 'canceled',
            'checkout_url' => null,
            'sandbox_checkout_url' => null,
            'canceled_at' => $subscription->canceled_at ?? now(),
            'last_notified_at' => now(),
        ]);

        $subscription->save();

        $this->refreshUserSummary($subscription->user()->firstOrFail());

        return $subscription->fresh();
    }
}
