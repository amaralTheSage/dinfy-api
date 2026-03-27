<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Subscriptions\SubscriptionCatalog;
use App\Services\Subscriptions\SubscriptionCheckoutSessionStore;
use App\Services\Subscriptions\SubscriptionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionCheckoutSessionController extends Controller
{
    public function __construct(
        private readonly SubscriptionCatalog $catalog,
        private readonly SubscriptionCheckoutSessionStore $sessions,
        private readonly SubscriptionManager $subscriptions,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('1. Entrou em SubscriptionCheckoutSessionController@store');

        try {
            $validated = $request->validate([
                'plan' => ['required', 'string', Rule::in(array_keys(config('subscriptions.plans', [])))],
            ]);
        } catch (ValidationException $e) {
            Log::warning('2. Falha na validacao em SubscriptionCheckoutSessionController@store', [
                'errors' => $e->errors(),
            ]);

            throw $e;
        }

        $planCode = (string) $validated['plan'];
        Log::info('2. Validacao concluida em SubscriptionCheckoutSessionController@store', [
            'plan' => $planCode,
        ]);

        $plan = $this->catalog->get($planCode);
        if (!$plan) {
            Log::warning('3. Plano nao encontrado em SubscriptionCheckoutSessionController@store', [
                'plan' => $planCode,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        if (($plan['checkout_mode'] ?? 'subscription_pending') !== 'subscription_authorized') {
            Log::warning('3. Plano sem checkout tokenizado em SubscriptionCheckoutSessionController@store', [
                'plan' => $planCode,
            ]);

            return response()->json([
                'message' => 'Este plano nao usa o checkout com cartao tokenizado.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $session = $this->sessions->create($request->user(), $planCode);

        Log::info('4. Sessao de checkout criada em SubscriptionCheckoutSessionController@store', [
            'session_id' => $session['id'],
            'plan' => $planCode,
        ]);

        return response()->json([
            'session' => [
                'id' => $session['id'],
                'plan' => $planCode,
                'checkout_page_url' => route('subscription.checkout.page', ['session' => $session['id']]),
                'expires_at' => $session['expires_at']->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function intro(string $session)
    {
        Log::info('1. Entrou em SubscriptionCheckoutSessionController@intro', [
            'session' => $session,
        ]);

        return view('subscriptions.intro', [
            ...$this->checkoutViewData($session),
            'checkoutUrl' => route('subscription.checkout.form', ['session' => $session]),
        ]);
    }

    public function show(string $session)
    {
        Log::info('1. Entrou em SubscriptionCheckoutSessionController@show', [
            'session' => $session,
        ]);

        return view('subscriptions.checkout', $this->checkoutViewData($session));
    }

    public function complete(Request $request, string $session): JsonResponse
    {
        Log::info('1. Entrou em SubscriptionCheckoutSessionController@complete', [
            'session' => $session,
        ]);

        try {
            $validated = $request->validate([
                'card_token_id' => ['required', 'string'],
                'device_session_id' => ['nullable', 'string'],
            ]);
        } catch (ValidationException $e) {
            Log::warning('2. Falha na validacao em SubscriptionCheckoutSessionController@complete', [
                'session' => $session,
                'errors' => $e->errors(),
            ]);

            throw $e;
        }

        Log::info('2. Validacao concluida em SubscriptionCheckoutSessionController@complete', [
            'session' => $session,
        ]);

        $sessionPayload = $this->sessions->find($session);
        if (!$sessionPayload) {
            Log::warning('3. Sessao nao encontrada em SubscriptionCheckoutSessionController@complete', [
                'session' => $session,
            ]);

            return response()->json([
                'message' => 'A sessao de pagamento expirou. Volte ao app e tente novamente.',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = User::query()->find($sessionPayload['user_id']);
        if (!$user) {
            Log::warning('4. Usuario da sessao nao encontrado em SubscriptionCheckoutSessionController@complete', [
                'session' => $session,
                'user_id' => $sessionPayload['user_id'],
            ]);

            $this->sessions->forget($session);

            return response()->json([
                'message' => 'Nao foi possivel localizar o usuario desta sessao.',
            ], Response::HTTP_NOT_FOUND);
        }

        Log::info('5. Chamando SubscriptionManager@createCheckout a partir de SubscriptionCheckoutSessionController@complete', [
            'session' => $session,
            'user_id' => $user->id,
            'plan' => $sessionPayload['plan_code'],
        ]);

        $subscription = $this->subscriptions->createCheckout(
            $user,
            (string) $sessionPayload['plan_code'],
            null,
            (string) $validated['card_token_id'],
            isset($validated['device_session_id']) ? (string) $validated['device_session_id'] : null,
        );

        $this->sessions->forget($session);

        Log::info('9. SubscriptionCheckoutSessionController@complete finalizado com sucesso', [
            'session' => $session,
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
        ]);

        return response()->json([
            'subscription' => [
                'plan' => $subscription->plan_code,
                'status' => $subscription->status,
            ],
            'redirect_url' => $this->buildAppReturnUrl([
                'checkout_status' => 'success',
                'message' => 'Assinatura criada com sucesso.',
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutViewData(string $session): array
    {
        Log::info('2. Carregando dados da sessao em SubscriptionCheckoutSessionController@checkoutViewData', [
            'session' => $session,
        ]);

        $sessionPayload = $this->sessions->find($session);
        if (!$sessionPayload) {
            Log::warning('3. Sessao nao encontrada em SubscriptionCheckoutSessionController@checkoutViewData', [
                'session' => $session,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        $user = User::query()->find($sessionPayload['user_id']);
        $plan = $this->catalog->get($sessionPayload['plan_code']);
        $publicKey = trim((string) config('services.mercadopago.public_key', ''));

        if (!$user || !$plan) {
            Log::warning('4. Dados invalidos da sessao em SubscriptionCheckoutSessionController@checkoutViewData', [
                'session' => $session,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        Log::info('5. Dados da sessao carregados em SubscriptionCheckoutSessionController@checkoutViewData', [
            'session' => $session,
            'user_id' => $user->id,
            'plan' => $plan['code'] ?? null,
        ]);

        return [
            'sessionId' => $session,
            'plan' => $plan,
            'user' => $user,
            'publicKey' => $publicKey,
            'completionUrl' => route('subscription.checkout.complete', ['session' => $session]),
            'returnUrl' => $this->buildAppReturnUrl(),
            'errorMessage' => $publicKey === '' ? 'Configure a chave publica do Mercado Pago para liberar o checkout.' : null,
        ];
    }

    /**
     * @param array<string, string> $query
     */
    private function buildAppReturnUrl(array $query = []): string
    {
        $base = trim((string) config('subscriptions.app_return_url', 'dinfy://subscription'));
        if ($query === []) {
            return $base;
        }

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base . $separator . http_build_query($query);
    }
}
