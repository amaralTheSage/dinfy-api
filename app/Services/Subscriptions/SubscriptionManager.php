<?php

namespace App\Services\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionManager
{
    private const STATUS_PRIORITY = "
        case
            when status = 'active' then 0
            when status = 'pending' then 1
            when status = 'expired' then 2
            when status = 'canceled' then 3
            else 4
        end
    ";

    private const PAYMENT_PENDING_STATUSES = ['pending', 'in_process'];

    private const PAYMENT_APPROVED_STATUSES = ['approved', 'accredited'];

    private const PAYMENT_CANCELED_STATUSES = ['canceled', 'rejected', 'refunded', 'charged_back'];

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

    public function currentForUser(
        User $user,
        bool $sync = false,
        bool $recoverExpiredCheckout = false,
    ): ?UserSubscription {
        $subscription = $this->currentSubscriptionQuery($user)->first();

        if ($subscription && $this->shouldExpireDueSubscription($subscription)) {
            $subscription = $this->expireDueSubscription($subscription);
        }

        if ($sync && $subscription && $subscription->status->isOpen()) {
            $subscription = $this->syncCurrentSubscription($subscription) ?? $subscription->fresh();

            if ($subscription && $this->shouldExpireDueSubscription($subscription)) {
                $subscription = $this->expireDueSubscription($subscription);
            }
        }

        if ($recoverExpiredCheckout && $subscription && $subscription->status === SubscriptionStatus::Expired) {
            return $this->recoverExpiredCheckout($user, $subscription);
        }

        return $subscription;
    }

    public function createCheckout(
        User $user,
        string $planCode,
        ?string $payerEmail = null,
        ?string $payerDocument = null,
        ?string $payerDocumentType = 'CPF',
    ): UserSubscription {
        Log::info('3. Entrou em SubscriptionManager@createCheckout', [
            'user_id' => $user->id,
            'plan' => $planCode,
        ]);

        $plan = $this->requirePlan($user, $planCode);
        $this->ensureUserDoesNotHaveOpenSubscription($user);

        $externalReference = $this->generateExternalReference($user, $planCode);

        Log::info('4. Validacoes de negocio concluidas em SubscriptionManager@createCheckout', [
            'user_id' => $user->id,
            'plan' => $planCode,
            'external_reference' => $externalReference,
        ]);

        $resolvedPayerEmail = $this->requirePendingPayerEmail($payerEmail ?? $user->email);
        $resolvedPayerDocument = $this->requirePendingPayerDocument(
            $payerDocument,
            $payerDocumentType ?? 'CPF',
        );

        return $this->createPixCheckout(
            $user,
            $plan,
            $externalReference,
            $resolvedPayerEmail,
            $resolvedPayerDocument['number'],
            $resolvedPayerDocument['type'],
        );
    }

    private function createPixCheckout(
        User $user,
        array $plan,
        string $externalReference,
        string $payerEmail,
        string $payerDocumentNumber,
        string $payerDocumentType,
    ): UserSubscription {
        [$payerFirstName, $payerLastName] = $this->splitPayerName($user->name);

        $payment = $this->mercadoPago->createPixPayment([
            'transaction_amount' => $plan['amount'],
            'external_reference' => $externalReference,
            'payer_email' => $payerEmail,
            'payer_first_name' => $payerFirstName,
            'payer_last_name' => $payerLastName,
            'payer_identification_number' => $payerDocumentNumber,
            'payer_identification_type' => $payerDocumentType,
            'description' => $plan['reason'] ?? 'Dinfy - ' . ($plan['name'] ?? ''),
            'notification_url' => config('subscriptions.notification_url'),
        ]);

        $subscription = DB::transaction(function () use (
            $user,
            $plan,
            $externalReference,
            $payment,
            $payerDocumentNumber,
            $payerDocumentType,
        ): UserSubscription {
            $subscription = UserSubscription::query()->create([
                'user_id' => $user->id,
                'provider' => 'pix',
                'plan_code' => (string) $plan['code'],
                'status' => SubscriptionStatus::Pending,
                'external_reference' => $externalReference,
                'transaction_amount' => (float) $plan['amount'],
                'currency_id' => (string) $plan['currency_id'],
                'frequency' => (int) $plan['frequency'],
                'frequency_type' => (string) $plan['frequency_type'],
                'payer_document_type' => $payerDocumentType,
                'payer_document_number' => $payerDocumentNumber,
            ]);

            SubscriptionInvoice::query()->create([
                'user_subscription_id' => $subscription->id,
                'provider' => 'mercado_pago',
                'provider_payment_id' => (string) Arr::get($payment, 'id', ''),
                'external_reference' => $externalReference,
                'transaction_amount' => (float) $plan['amount'],
                'currency_id' => $plan['currency_id'],
                'status' => (string) Arr::get($payment, 'status', 'pending'),
                'status_detail' => (string) Arr::get($payment, 'status_detail'),
                'expires_at' => $this->parseDate(Arr::get($payment, 'date_of_expiration')),
                'qr_code' => (string) Arr::get($payment, 'point_of_interaction.transaction_data.qr_code'),
                'qr_code_base64' => (string) Arr::get($payment, 'point_of_interaction.transaction_data.qr_code_base64'),
                'qr_code_expires_at' => $this->parseDate(Arr::get($payment, 'point_of_interaction.transaction_data.expiration_date')),
            ]);

            return $subscription;
        });

        return $this->applyPaymentPayload($subscription, $payment, (string) Arr::get($payment, 'id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function requirePlan(User $user, string $planCode): array
    {
        $plan = $this->catalog->get($planCode);
        if ($plan) {
            return $plan;
        }

        Log::warning('4. Plano invalido em SubscriptionManager@createCheckout', [
            'user_id' => $user->id,
            'plan' => $planCode,
        ]);

        throw ValidationException::withMessages([
            'plan' => ['Plano de assinatura invalido.'],
        ]);
    }

    private function ensureUserDoesNotHaveOpenSubscription(User $user): void
    {
        $current = $this->currentOpenSubscription($user);
        if (! $current) {
            return;
        }

        Log::warning('4. Usuario ja possui assinatura em aberto em SubscriptionManager@createCheckout', [
            'user_id' => $user->id,
            'current_subscription_id' => $current->id,
            'current_status' => $current->status->value,
        ]);

        throw ValidationException::withMessages([
            'plan' => ['Ja existe uma assinatura em andamento para este usuario.'],
        ]);
    }

    private function currentOpenSubscription(User $user): ?UserSubscription
    {
        $subscription = $this->currentSubscriptionQuery($user)->first();

        return $subscription && $subscription->status->isOpen()
            ? $subscription
            : null;
    }

    private function syncCurrentSubscription(UserSubscription $subscription): ?UserSubscription
    {


        if (
            $subscription->mercado_pago_payment_id
            && in_array((string) $subscription->latest_payment_status, self::PAYMENT_PENDING_STATUSES, true)
        ) {
            return $this->syncPayment($subscription->mercado_pago_payment_id);
        }

        return $subscription;
    }

    private function cancelPendingSubscription(UserSubscription $subscription): UserSubscription
    {
        Log::info('4. Assinatura pendente encontrada em SubscriptionManager@cancelCurrent', [
            'subscription_id' => $subscription->id,
        ]);

        if (
            $subscription->mercado_pago_payment_id
            && in_array(strtolower((string) $subscription->latest_payment_status), self::PAYMENT_PENDING_STATUSES, true)
        ) {
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

    private function requirePendingPayerEmail(?string $payerEmail): string
    {
        $resolved = trim((string) $payerEmail);
        Log::info('5. Validando payer_email em SubscriptionManager@requirePendingPayerEmail');

        if ($resolved === '') {
            Log::warning('5. Falha na validação do payer_email em SubscriptionManager@requirePendingPayerEmail');

            throw ValidationException::withMessages([
                'payer_email' => ['Informe um e-mail valido para gerar o pagamento PIX.'],
            ]);
        }

        return $resolved;
    }

    /**
     * @return array{type:string,number:string}
     */
    private function requirePendingPayerDocument(?string $payerDocument, string $payerDocumentType = 'CPF'): array
    {
        $resolvedType = strtoupper(trim($payerDocumentType));
        $normalizedNumber = preg_replace('/\D+/', '', (string) $payerDocument) ?? '';

        Log::info('5. Validando payer_document em SubscriptionManager@requirePendingPayerDocument', [
            'type' => $resolvedType,
            'length' => strlen($normalizedNumber),
        ]);

        if ($resolvedType === '') {
            $resolvedType = 'CPF';
        }

        if (! $this->isValidPayerDocument($resolvedType, $normalizedNumber)) {
            $documentLabel = match ($resolvedType) {
                'CNPJ' => 'CNPJ',
                'CPF' => 'CPF',
                default => 'documento',
            };

            throw ValidationException::withMessages([
                'payer_document' => ["Informe um {$documentLabel} valido para gerar o pagamento PIX."],
            ]);
        }

        return [
            'type' => $resolvedType,
            'number' => $normalizedNumber,
        ];
    }

    private function isValidPayerDocument(string $documentType, string $documentNumber): bool
    {
        return match ($documentType) {
            'CPF' => $this->isValidCpf($documentNumber),
            'CNPJ' => $this->isValidCnpj($documentNumber),
            default => false,
        };
    }

    private function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($checkDigitIndex = 9; $checkDigitIndex < 11; $checkDigitIndex++) {
            $sum = 0;

            for ($digitIndex = 0; $digitIndex < $checkDigitIndex; $digitIndex++) {
                $sum += ((int) $cpf[$digitIndex]) * (($checkDigitIndex + 1) - $digitIndex);
            }

            $remainder = ($sum * 10) % 11;
            $expectedDigit = $remainder === 10 ? 0 : $remainder;

            if ((int) $cpf[$checkDigitIndex] !== $expectedDigit) {
                return false;
            }
        }

        return true;
    }

    private function isValidCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        $firstWeights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $secondWeights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $firstDigit = $this->calculateCnpjCheckDigit($cnpj, $firstWeights);
        $secondDigit = $this->calculateCnpjCheckDigit($cnpj, $secondWeights);

        return (int) $cnpj[12] === $firstDigit
            && (int) $cnpj[13] === $secondDigit;
    }

    /**
     * @param array<int, int> $weights
     */
    private function calculateCnpjCheckDigit(string $cnpj, array $weights): int
    {
        $sum = 0;

        foreach ($weights as $index => $weight) {
            $sum += ((int) $cnpj[$index]) * $weight;
        }

        $remainder = $sum % 11;

        return $remainder < 2 ? 0 : 11 - $remainder;
    }

    private function shouldExpireDueSubscription(?UserSubscription $subscription, ?Carbon $now = null): bool
    {
        if (! $subscription || ! $subscription->status->canExpire() || !$subscription->next_payment_at) {
            return false;
        }

        $resolvedNow = $now?->copy() ?? now();

        return $subscription->next_payment_at->lessThanOrEqualTo($resolvedNow);
    }

    public function expireDueSubscription(
        UserSubscription $subscription,
        ?Carbon $expiredAt = null,
    ): UserSubscription {
        $resolvedExpiredAt = $expiredAt?->copy() ?? now();

        $subscription->forceFill([
            'status' => SubscriptionStatus::Expired,
            'next_payment_at' => null,
            'canceled_at' => null,
            'latest_payment_status' => 'expired',
            'latest_payment_status_detail' => 'payment_overdue',
            'last_notified_at' => $resolvedExpiredAt,
        ]);

        return $this->persistSubscription($subscription);
    }

    private function recoverExpiredCheckout(
        User $user,
        UserSubscription $subscription,
    ): UserSubscription {
        if (
            $subscription->status !== SubscriptionStatus::Expired
            || trim((string) $subscription->plan_code) === ''
            || trim((string) $subscription->payer_document_number) === ''
        ) {
            return $subscription;
        }

        try {
            $recovered = $this->createCheckout(
                $user,
                (string) $subscription->plan_code,
                $user->email,
                (string) $subscription->payer_document_number,
                (string) ($subscription->payer_document_type ?: 'CPF'),
            );

            $recovered->setAttribute('recovered_from_expired', true);

            return $recovered;
        } catch (ValidationException $exception) {
            if ($this->currentOpenSubscription($user)) {
                $current = $this->currentSubscriptionQuery($user)->first() ?? $subscription;
                $current->setAttribute('recovered_from_expired', true);

                return $current;
            }

            Log::warning('Expired subscription recovery validation failed.', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'errors' => $exception->errors(),
            ]);

            return $subscription->fresh() ?? $subscription;
        } catch (\Throwable $exception) {
            Log::warning('Expired subscription recovery failed.', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'message' => $exception->getMessage(),
            ]);

            return $subscription->fresh() ?? $subscription;
        }
    }



    public function cancelCurrent(User $user): UserSubscription
    {
        Log::info('2. Entrou em SubscriptionManager@cancelCurrent', [
            'user_id' => $user->id,
        ]);

        $subscription = $this->currentOpenSubscription($user);

        if (! $subscription) {
            Log::warning('. Nenhuma assinatura cancelavel encontrada em SubscriptionManager@cancelCurrent', [
                'user_id' => $user->id,
            ]);

            throw ValidationException::withMessages([
                'subscription' => ['Nenhuma assinatura ativa foi encontrada para cancelamento.'],
            ]);
        }

        if ($subscription->status === SubscriptionStatus::Pending) {
            return $this->cancelPendingSubscription($subscription);
        }


        return $this->cancelLocalStandaloneSubscription($subscription);
    }

    public function handleWebhook(Request $request): void
    {
        Log::info('2. Entrou em SubscriptionManager@handleWebhook');

        if (
            $this->mercadoPago->hasWebhookSecret()
            && $this->shouldValidateWebhookSignature($request)
            && ! $this->mercadoPago->isValidWebhookSignature($request)
        ) {
            Log::warning('3. Assinatura do webhook invalida em SubscriptionManager@handleWebhook');

            abort(401, 'Invalid Mercado Pago signature.');
        }

        $type = $this->resolveWebhookType($request);
        $resourceId = $this->resolveWebhookResourceId($request);

        match ($type) {
            'payment' => $resourceId !== '' ? $this->syncPayment($resourceId) : null,
            default => Log::info('Mercado Pago webhook ignored.', [
                'type' => $type,
                'resource_id' => $resourceId,
            ]),
        };
    }

    private function shouldValidateWebhookSignature(Request $request): bool
    {
        $hasWebhookDataId = $this->normalizeWebhookResourceId(
            $request->query('data.id') ?? data_get($request->all(), 'data.id'),
        ) !== '';

        if ($hasWebhookDataId) {
            return true;
        }

        $hasIpnTopic = trim((string) ($request->query('topic') ?? $request->input('topic') ?? '')) !== '';
        $hasIpnResourceId = $this->normalizeWebhookResourceId(
            $request->query('id')
            ?? $request->input('id')
            ?? $request->input('resource'),
        ) !== '';

        if ($hasIpnTopic && $hasIpnResourceId) {
            Log::info('Mercado Pago IPN received; skipping webhook signature validation.', [
                'topic' => $request->query('topic') ?? $request->input('topic'),
                'resource_id' => $request->query('id') ?? $request->input('id') ?? $request->input('resource'),
            ]);

            return false;
        }

        return true;
    }

    private function resolveWebhookType(Request $request): string
    {
        return strtolower(trim((string) (
            $request->input('type')
            ?? $request->query('type')
            ?? $request->input('topic')
            ?? $request->query('topic')
            ?? ''
        )));
    }

    private function resolveWebhookResourceId(Request $request): string
    {
        $candidates = [
            $request->query('data.id'),
            data_get($request->all(), 'data.id'),
            $request->query('id'),
            $request->input('id'),
            $request->input('resource'),
        ];

        foreach ($candidates as $candidate) {
            $resolved = $this->normalizeWebhookResourceId($candidate);

            if ($resolved !== '') {
                return $resolved;
            }
        }

        return '';
    }

    private function normalizeWebhookResourceId(mixed $value): string
    {
        $resolved = trim((string) $value);

        if ($resolved === '') {
            return '';
        }

        if (filter_var($resolved, FILTER_VALIDATE_URL)) {
            $path = (string) parse_url($resolved, PHP_URL_PATH);
            $lastSegment = trim((string) basename($path));

            return $lastSegment !== '' ? $lastSegment : $resolved;
        }

        return $resolved;
    }

    public function syncPayment(string $paymentId): ?UserSubscription
    {
        Log::info('4. Entrou em SubscriptionManager@syncPayment', [
            'payment_id' => $paymentId,
        ]);

        $payload = $this->mercadoPago->fetchPayment($paymentId);
        $subscription = $this->resolveSubscriptionFromPaymentPayload($payload, $paymentId);

        if (! $subscription) {
            Log::info('Mercado Pago payment ignored because no local subscription was found.', [
                'payment_id' => $paymentId,
                'external_reference' => Arr::get($payload, 'external_reference'),
            ]);

            return null;
        }

        $this->syncSubscriptionInvoice($subscription, $payload, $paymentId);

        $subscription = $this->applyPaymentPayload($subscription, $payload, $paymentId);



        return $subscription;
    }

    private function syncSubscriptionInvoice(UserSubscription $subscription, array $payload, string $providerPaymentId): void
    {
        $invoice = SubscriptionInvoice::query()
            ->where('provider_payment_id', $providerPaymentId)
            ->first();

        if (! $invoice) {
            $invoice = SubscriptionInvoice::query()->create([
                'user_subscription_id' => $subscription->id,
                'provider' => 'mercado_pago',
                'provider_payment_id' => $providerPaymentId,
                'external_reference' => $subscription->external_reference,
                'transaction_amount' => (float) Arr::get($payload, 'transaction_amount', $subscription->transaction_amount),
                'currency_id' => (string) Arr::get($payload, 'currency_id', $subscription->currency_id),
                'status' => (string) Arr::get($payload, 'status', 'pending'),
                'status_detail' => (string) Arr::get($payload, 'status_detail'),
            ]);
        }

        $status = (string) Arr::get($payload, 'status', $invoice->status);

        $invoice->forceFill([
            'provider_payment_id' => $providerPaymentId,
            'external_reference' => (string) Arr::get($payload, 'external_reference', $invoice->external_reference),
            'transaction_amount' => $this->moneyValue(
                Arr::get($payload, 'transaction_amount'),
                $invoice->transaction_amount,
            ),
            'currency_id' => (string) Arr::get($payload, 'currency_id', $invoice->currency_id),
            'status' => $status,
            'status_detail' => (string) Arr::get($payload, 'status_detail', $invoice->status_detail),
            'expires_at' => $this->parseDate(Arr::get($payload, 'date_of_expiration')) ?? $invoice->expires_at,
            'paid_at' => in_array($status, self::PAYMENT_APPROVED_STATUSES, true)
                ? ($this->paymentApprovedAt($payload) ?? $invoice->paid_at ?? now())
                : $invoice->paid_at,
            'canceled_at' => in_array($status, self::PAYMENT_CANCELED_STATUSES, true)
                ? ($this->paymentCanceledAt($payload) ?? $invoice->canceled_at ?? now())
                : $invoice->canceled_at,
            'qr_code' => Arr::get($payload, 'point_of_interaction.transaction_data.qr_code', $invoice->qr_code),
            'qr_code_base64' => Arr::get($payload, 'point_of_interaction.transaction_data.qr_code_base64', $invoice->qr_code_base64),
            'qr_code_expires_at' => $this->parseDate(Arr::get($payload, 'point_of_interaction.transaction_data.expiration_date')) ?? $invoice->qr_code_expires_at,
        ])->save();
    }

    private function currentSubscriptionQuery(User $user): HasMany
    {
        return $user->subscriptions()
            ->orderByRaw(self::STATUS_PRIORITY)
            ->latest('created_at');
    }

    private function resolveSubscriptionFromPaymentPayload(array $payload, string $paymentId): ?UserSubscription
    {
        $externalReference = (string) Arr::get($payload, 'external_reference');

        if ($externalReference === '' && $paymentId === '') {
            return null;
        }

        $query = UserSubscription::query();

        if ($externalReference !== '') {
            $query->where('external_reference', $externalReference);
        }

        if ($paymentId !== '') {
            if ($externalReference !== '') {
                $query->orWhere('mercado_pago_payment_id', $paymentId);
            } else {
                $query->where('mercado_pago_payment_id', $paymentId);
            }
        }

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $payload
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

        $approvedAt = $this->paymentApprovedAt($payload);
        $startedAt = $subscription->started_at
            ?? $approvedAt
            ?? $this->parseDate(Arr::get($payload, 'payment.date_created'))
            ?? $this->parseDate(Arr::get($payload, 'date_created'));

        $status = $subscription->status;
        $nextPaymentAt = $subscription->next_payment_at;
        $canceledAt = $subscription->canceled_at;


        if (in_array(strtolower($paymentStatus), self::PAYMENT_APPROVED_STATUSES, true) && $approvedAt) {
            $startedAt = $approvedAt;
        }

        [$status, $nextPaymentAt, $canceledAt] = $this->resolveStandalonePaymentState(
            $subscription,
            $paymentStatus,
            $startedAt,
            $payload,
        );

        $subscription->forceFill([
            'status' => $status,
            'mercado_pago_payment_id' => $paymentId !== '' ? $paymentId : $subscription->mercado_pago_payment_id,
            'latest_payment_status' => $paymentStatus !== '' ? $paymentStatus : $subscription->latest_payment_status,
            'latest_payment_status_detail' => $paymentStatusDetail !== '' ? $paymentStatusDetail : $subscription->latest_payment_status_detail,
            'started_at' => $startedAt,
            'next_payment_at' => $nextPaymentAt,
            'canceled_at' => $canceledAt,
            'last_notified_at' => now(),
        ]);

        Log::info('9. Payload de pagamento aplicado em SubscriptionManager@applyPaymentPayload', [
            'subscription_id' => $subscription->id,
            'status' => $status->value,
            'payment_status' => $paymentStatus,
        ]);

        return $this->persistSubscription($subscription);
    }

    private function persistSubscription(UserSubscription $subscription): UserSubscription
    {
        $subscription->save();
        $this->syncUserSummary($subscription->user()->firstOrFail());

        return $subscription->fresh();
    }

    public function syncUserSummary(User $user): void
    {
        Log::info('8. Atualizando resumo da assinatura do usuario em SubscriptionManager@syncUserSummary', [
            'user_id' => $user->id,
        ]);

        $current = $this->currentSubscriptionQuery($user)->first();

        if (! $current) {
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
            'subscription_provider' => $current->provider ?? 'pix',
            'subscription_plan' => $current->plan_code,
            'subscription_status' => $current->status->value,
            'subscription_reference' => $current->external_reference,
            'subscription_started_at' => $current->started_at,
            'subscription_renews_at' => $current->next_payment_at,
            'subscription_canceled_at' => $current->canceled_at,
        ])->save();
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function splitPayerName(?string $fullName): array
    {
        $parts = preg_split('/\s+/', trim((string) $fullName)) ?: [];
        $parts = array_values(array_filter($parts, fn(string $part): bool => $part !== ''));

        if ($parts === []) {
            return [null, null];
        }

        $firstName = $parts[0];
        $lastName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;

        return [$firstName, $lastName];
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

    private function parseDate(mixed $value): ?Carbon
    {
        if (! $value) {
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
     * @param  array<string, mixed>  $payload
     * @return array{0:SubscriptionStatus,1:?Carbon,2:?Carbon}
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
                SubscriptionStatus::Active,
                $this->calculateStandaloneRenewalAt($subscription, $startedAt) ?? $subscription->next_payment_at,
                null,
            ],
            'pending', 'in_process' => [
                SubscriptionStatus::Pending,
                $subscription->next_payment_at,
                $subscription->canceled_at,
            ],
            'canceled', 'rejected', 'refunded', 'charged_back' => [
                SubscriptionStatus::Canceled,
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
        if (! $startedAt || $subscription->frequency <= 0) {
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
     * @param  array<string, mixed>  $payload
     */
    private function paymentApprovedAt(array $payload): ?Carbon
    {
        return $this->parseDate(Arr::get($payload, 'payment.date_approved'))
            ?? $this->parseDate(Arr::get($payload, 'date_approved'))
            ?? $this->parseDate(Arr::get($payload, 'date_last_updated'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function paymentCanceledAt(array $payload): ?Carbon
    {
        return $this->parseDate(Arr::get($payload, 'date_last_updated'))
            ?? $this->parseDate(Arr::get($payload, 'payment.date_last_updated'))
            ?? $this->parseDate(Arr::get($payload, 'date_created'));
    }

    private function cancelLocalStandaloneSubscription(UserSubscription $subscription): UserSubscription
    {
        Log::info('8. Cancelando assinatura local sem recorrencia do Mercado Pago', [
            'subscription_id' => $subscription->id,
        ]);

        $subscription->forceFill([
            'status' => SubscriptionStatus::Canceled,
            'next_payment_at' => null,
            'canceled_at' => $subscription->canceled_at ?? now(),
            'last_notified_at' => now(),
        ]);

        return $this->persistSubscription($subscription);
    }

    private function cancelLocalPendingCheckout(UserSubscription $subscription): UserSubscription
    {
        Log::info('8. Cancelando assinatura pendente local em SubscriptionManager@cancelLocalPendingCheckout', [
            'subscription_id' => $subscription->id,
        ]);

        $subscription->forceFill([
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => $subscription->canceled_at ?? now(),
            'last_notified_at' => now(),
        ]);

        return $this->persistSubscription($subscription);
    }
}
