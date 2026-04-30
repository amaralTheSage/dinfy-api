<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use WorkOS\Exception\WorkOSException;
use WorkOS\Resource\UserManagementAuthenticationProvider;
use WorkOS\Resource\UserManagementAuthenticationScreenHint;
use WorkOS\WorkOS;

class SocialAuthService
{
    private const PROVIDER = 'workos';

    public function __construct(
        private readonly WorkOS $workos,
        private readonly WorkosAuthService $workosAuth,
    ) {}

    public function validateRedirectUri(string $redirectUri): string
    {
        $redirectUri = trim($redirectUri);
        $allowedRedirectUris = $this->allowedRedirectUris();

        if ($redirectUri === '' || ! in_array($redirectUri, $allowedRedirectUris, true)) {
            throw new SocialAuthException('O destino de retorno do login nao e permitido.');
        }

        return $redirectUri;
    }

    public function resolveRedirectUri(mixed $redirectUri): string
    {
        $redirectUri = trim((string) $redirectUri);

        if ($redirectUri === '') {
            return $this->fallbackRedirectUri();
        }

        return $this->validateRedirectUri($redirectUri);
    }

    public function createAuthorizationState(string $provider, string $redirectUri): string
    {
        $this->ensureSupportedProvider($provider);

        $state = Str::random(64);

        Cache::put(
            $this->stateCacheKey($state),
            [
                'provider' => $provider,
                'redirect_uri' => $redirectUri,
            ],
            now()->addMinutes($this->stateTtlMinutes())
        );

        return $state;
    }

    public function pullAuthorizationState(string $provider, ?string $state): ?array
    {
        $state = trim((string) $state);
        if ($state === '') {
            return null;
        }

        $payload = Cache::pull($this->stateCacheKey($state));
        if (! is_array($payload) || ($payload['provider'] ?? null) !== $provider) {
            return null;
        }

        return $payload;
    }

    public function authorizationUrl(
        string $provider,
        string $state,
        ?UserManagementAuthenticationScreenHint $screenHint = null,
        ?string $loginHint = null,
    ): string {
        $this->ensureSupportedProvider($provider);

        return $this->workosAuthorizationUrl($state, $screenHint, $loginHint);
    }

    public function exchangeCodeForIdentity(string $provider, string $code): SocialIdentity
    {
        $this->ensureSupportedProvider($provider);

        return $this->workosIdentityFromCode($code);
    }

    public function resolveUser(string $provider, SocialIdentity $identity): User
    {
        $this->ensureSupportedProvider($provider);

        return $this->workosAuth->syncLocalUserFromIdentity($identity);
    }

    public function issueExchangeCode(User $user, string $provider): string
    {
        $this->ensureSupportedProvider($provider);

        $code = Str::random(64);

        Cache::put(
            $this->exchangeCodeCacheKey($code),
            [
                'user_id' => $user->id,
                'provider' => $provider,
            ],
            now()->addMinutes($this->exchangeCodeTtlMinutes())
        );

        return $code;
    }

    public function consumeExchangeCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $payload = Cache::pull($this->exchangeCodeCacheKey($code));

        return is_array($payload) ? $payload : null;
    }

    public function fallbackRedirectUri(): string
    {
        $fallback = trim((string) config('services.oauth.fallback_redirect_uri', ''));
        if ($fallback !== '') {
            return $fallback;
        }

        return Arr::first($this->allowedRedirectUris()) ?? 'dinfy://auth-callback';
    }

    public function buildFrontendRedirect(string $baseUrl, array $parameters): string
    {
        $baseUrl = trim($baseUrl);
        $fragment = '';

        if (str_contains($baseUrl, '#')) {
            [$baseUrl, $rawFragment] = explode('#', $baseUrl, 2);
            $fragment = '#'.$rawFragment;
        }

        $query = http_build_query(array_filter(
            $parameters,
            static fn ($value): bool => $value !== null && $value !== ''
        ));

        if ($query === '') {
            return $baseUrl.$fragment;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.$query.$fragment;
    }

    public function callbackUrl(string $provider): string
    {
        return route('auth.oauth.callback', ['provider' => $provider]);
    }

    public function resolveScreenHint(mixed $screenHint): ?UserManagementAuthenticationScreenHint
    {
        $screenHint = Str::lower(trim((string) $screenHint));

        return match ($screenHint) {
            '', 'sign-in', 'signin', 'login' => $screenHint === ''
                ? null
                : UserManagementAuthenticationScreenHint::SignIn,
            'sign-up', 'signup', 'register' => UserManagementAuthenticationScreenHint::SignUp,
            default => throw new SocialAuthException('A tela de autenticacao solicitada e invalida.'),
        };
    }

    public function resolveLoginHint(mixed $loginHint): ?string
    {
        $loginHint = trim((string) $loginHint);

        return $loginHint !== '' ? $loginHint : null;
    }

    private function ensureSupportedProvider(string $provider): void
    {
        if ($provider !== self::PROVIDER) {
            throw new SocialAuthException('Provedor de login social invalido.');
        }
    }

    private function workosAuthorizationUrl(
        string $state,
        ?UserManagementAuthenticationScreenHint $screenHint = null,
        ?string $loginHint = null,
    ): string {
        if (! $this->hasWorkosCredentials()) {
            throw new SocialAuthException('O login com WorkOS nao esta configurado.');
        }

        try {
            return $this->workos->userManagement()->getAuthorizationUrl(
                redirectUri: $this->callbackUrl(self::PROVIDER),
                screenHint: $screenHint ?? UserManagementAuthenticationScreenHint::SignIn,
                loginHint: $loginHint,
                provider: UserManagementAuthenticationProvider::Authkit,
                state: $state,
            );
        } catch (WorkOSException|\Throwable $exception) {
            throw new SocialAuthException('Nao foi possivel iniciar o login com WorkOS.');
        }
    }

    private function workosIdentityFromCode(string $code): SocialIdentity
    {
        if (! $this->hasWorkosCredentials()) {
            throw new SocialAuthException('O login com WorkOS nao esta configurado.');
        }

        try {
            $response = $this->workos->userManagement()->authenticateWithCode(
                code: $code,
                ipAddress: request()->ip(),
                userAgent: request()->userAgent(),
            );
        } catch (WorkOSException|\Throwable $exception) {
            throw new SocialAuthException('Nao foi possivel concluir o login com WorkOS.');
        }

        $user = $response->user;
        $providerId = trim($user->id);
        $email = Str::lower(trim($user->email));
        $name = trim(implode(' ', array_filter([
            trim((string) ($user->firstName ?? '')),
            trim((string) ($user->lastName ?? '')),
        ])));
        $picture = trim((string) ($user->profilePictureUrl ?? ''));
        $emailVerified = $user->emailVerified;

        if ($providerId === '' || $email === '') {
            throw new SocialAuthException('Sua conta do WorkOS precisa informar um e-mail valido.');
        }

        if (! $emailVerified) {
            throw new SocialAuthException('Sua conta do WorkOS precisa ter um e-mail verificado para entrar.');
        }

        return new SocialIdentity(
            provider: self::PROVIDER,
            providerId: $providerId,
            email: $email,
            name: $name,
            avatar: $picture !== '' ? $picture : null,
            emailVerified: true,
        );
    }

    private function hasWorkosCredentials(): bool
    {
        return trim((string) config('services.workos.client_id', '')) !== ''
            && trim((string) config('services.workos.api_key', '')) !== '';
    }

    private function allowedRedirectUris(): array
    {
        $allowedRedirectUris = config('services.oauth.allowed_redirect_uris', []);

        return array_values(array_filter(
            is_array($allowedRedirectUris) ? $allowedRedirectUris : [],
            static fn ($value): bool => is_string($value) && trim($value) !== ''
        ));
    }

    private function stateTtlMinutes(): int
    {
        return max(1, (int) config('services.oauth.state_ttl_minutes', 10));
    }

    private function exchangeCodeTtlMinutes(): int
    {
        return max(1, (int) config('services.oauth.exchange_code_ttl_minutes', 5));
    }

    private function stateCacheKey(string $state): string
    {
        return 'social-oauth-state:'.$state;
    }

    private function exchangeCodeCacheKey(string $code): string
    {
        return 'social-oauth-exchange:'.$code;
    }
}
