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
                'message' => 'Este plano nao usa o checkout com cartao tokenizado.',
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

    public function intro(string $session)
    {
        return view('subscriptions.intro', [
            ...$this->checkoutViewData($session),
            'checkoutUrl' => route('subscription.checkout.form', ['session' => $session]),
        ]);
    }

    public function show(string $session)
    {
        return view('subscriptions.checkout', $this->checkoutViewData($session));
    }

    public function complete(Request $request, string $session): JsonResponse
    {
        $validated = $request->validate([
            'card_token_id' => ['required', 'string'],
        ]);

        $sessionPayload = $this->sessions->find($session);
        if (!$sessionPayload) {
            return response()->json([
                'message' => 'A sessao de pagamento expirou. Volte ao app e tente novamente.',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = User::query()->find($sessionPayload['user_id']);
        if (!$user) {
            $this->sessions->forget($session);

            return response()->json([
                'message' => 'Nao foi possivel localizar o usuario desta sessao.',
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
     * @return array<string, mixed>
     */
    private function checkoutViewData(string $session): array
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
