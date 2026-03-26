<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Subscriptions\SubscriptionCatalog;
use App\Services\Subscriptions\SubscriptionCheckoutSessionStore;
use App\Services\Subscriptions\SubscriptionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $validated = $request->validate([
            'plan' => ['required', 'string', Rule::in(array_keys(config('subscriptions.plans', [])))],
        ]);

        $planCode = (string) $validated['plan'];
        $plan = $this->catalog->get($planCode);
        if (!$plan) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (($plan['checkout_mode'] ?? 'subscription_pending') !== 'subscription_authorized') {
            return response()->json([
                'message' => 'Este plano não usa o checkout com cartão tokenizado.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $session = $this->sessions->create($request->user(), $planCode);

        return response()->json([
            'session' => [
                'id' => $session['id'],
                'plan' => $planCode,
                'checkout_page_url' => route('subscription.checkout.page', ['session' => $session['id']]),
                'expires_at' => $session['expires_at']->toIso8601String(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(string $session)
    {
        $sessionPayload = $this->sessions->find($session);
        if (!$sessionPayload) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $user = User::query()->find($sessionPayload['user_id']);
        $plan = $this->catalog->get($sessionPayload['plan_code']);
        $publicKey = trim((string) config('services.mercadopago.public_key', ''));

        if (!$user || !$plan) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return view('subscriptions.checkout', [
            'sessionId' => $session,
            'plan' => $plan,
            'user' => $user,
            'publicKey' => $publicKey,
            'completionUrl' => route('subscription.checkout.complete', ['session' => $session]),
            'returnUrl' => $this->buildAppReturnUrl(),
            'errorMessage' => $publicKey === '' ? 'Configure a chave pública do Mercado Pago para liberar o checkout.' : null,
        ]);
    }

    public function complete(Request $request, string $session): JsonResponse
    {
        $validated = $request->validate([
            'card_token_id' => ['required', 'string'],
        ]);

        $sessionPayload = $this->sessions->find($session);
        if (!$sessionPayload) {
            return response()->json([
                'message' => 'A sessão de pagamento expirou. Volte ao app e tente novamente.',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = User::query()->find($sessionPayload['user_id']);
        if (!$user) {
            $this->sessions->forget($session);

            return response()->json([
                'message' => 'Não foi possível localizar o usuário desta sessão.',
            ], Response::HTTP_NOT_FOUND);
        }

        $subscription = $this->subscriptions->createCheckout(
            $user,
            (string) $sessionPayload['plan_code'],
            null,
            (string) $validated['card_token_id'],
        );

        $this->sessions->forget($session);

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
