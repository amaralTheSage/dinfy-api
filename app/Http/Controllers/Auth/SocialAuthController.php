<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\SocialAuthException;
use App\Services\Auth\SocialAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SocialAuthController extends Controller
{
    public function redirect(Request $request, string $provider, SocialAuthService $socialAuth)
    {
        try {
            $redirectUri = $socialAuth->validateRedirectUri((string) $request->query('redirect_uri', ''));
            $state = $socialAuth->createAuthorizationState($provider, $redirectUri);

            return redirect()->away($socialAuth->authorizationUrl($provider, $state));
        } catch (SocialAuthException $exception) {
            $fallbackRedirectUri = $socialAuth->fallbackRedirectUri();

            return redirect()->away($socialAuth->buildFrontendRedirect($fallbackRedirectUri, [
                'provider' => $provider,
                'error' => 'oauth_unavailable',
                'message' => $exception->getMessage(),
            ]));
        }
    }

    public function callback(Request $request, string $provider, SocialAuthService $socialAuth)
    {
        $stateData = $socialAuth->pullAuthorizationState($provider, (string) $request->query('state', ''));
        $redirectUri = $stateData['redirect_uri'] ?? $socialAuth->fallbackRedirectUri();

        if ($request->filled('error')) {
            return redirect()->away($socialAuth->buildFrontendRedirect($redirectUri, [
                'provider' => $provider,
                'error' => (string) $request->query('error', 'oauth_denied'),
                'message' => (string) $request->query('error_description', 'Login cancelado.'),
            ]));
        }

        if (!$stateData) {
            return redirect()->away($socialAuth->buildFrontendRedirect($redirectUri, [
                'provider' => $provider,
                'error' => 'invalid_state',
                'message' => 'Sua sessao de login expirou. Tente novamente.',
            ]));
        }

        try {
            $authorizationCode = trim((string) $request->query('code', ''));
            if ($authorizationCode === '') {
                throw new SocialAuthException('O provedor nao retornou um codigo de autorizacao valido.');
            }

            $identity = $socialAuth->exchangeCodeForIdentity($provider, $authorizationCode);
            $user = $socialAuth->resolveUser($provider, $identity);
            $exchangeCode = $socialAuth->issueExchangeCode($user, $provider);

            return redirect()->away($socialAuth->buildFrontendRedirect($redirectUri, [
                'provider' => $provider,
                'oauth_code' => $exchangeCode,
            ]));
        } catch (SocialAuthException $exception) {
            return redirect()->away($socialAuth->buildFrontendRedirect($redirectUri, [
                'provider' => $provider,
                'error' => 'oauth_failed',
                'message' => $exception->getMessage(),
            ]));
        } catch (\Throwable $exception) {
            Log::error('Unexpected social login failure.', [
                'provider' => $provider,
                'message' => $exception->getMessage(),
            ]);

            return redirect()->away($socialAuth->buildFrontendRedirect($redirectUri, [
                'provider' => $provider,
                'error' => 'oauth_failed',
                'message' => 'Nao foi possivel concluir o login social agora.',
            ]));
        }
    }

    public function exchange(Request $request, SocialAuthService $socialAuth)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
        ]);

        $payload = $socialAuth->consumeExchangeCode($validated['code']);
        $userId = is_array($payload) ? ($payload['user_id'] ?? null) : null;

        /** @var User|null $user */
        $user = is_numeric($userId) ? User::query()->find((int) $userId) : null;

        if (!$user) {
            throw ValidationException::withMessages([
                'code' => ['O codigo de login e invalido ou expirou.'],
            ]);
        }

        $provider = is_array($payload) ? trim((string) ($payload['provider'] ?? 'oauth')) : 'oauth';
        $tokenName = $request->userAgent() ?: $provider.'-oauth';
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }
}
