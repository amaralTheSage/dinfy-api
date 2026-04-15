<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class SocialAuthService
{
    public function validateRedirectUri(string $redirectUri): string
    {
        $redirectUri = trim($redirectUri);
        $allowedRedirectUris = $this->allowedRedirectUris();

        if ($redirectUri === '' || !in_array($redirectUri, $allowedRedirectUris, true)) {
            throw new SocialAuthException('O destino de retorno do login nao e permitido.');
        }

        return $redirectUri;
    }

    public function createAuthorizationState(string $provider, string $redirectUri): string
    {
        if ($provider !== 'auth0') {
            throw new SocialAuthException('Provedor de login social invalido.');
        }

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
        if (!is_array($payload) || ($payload['provider'] ?? null) !== $provider) {
            return null;
        }

        return $payload;
    }

    public function authorizationUrl(string $provider, string $state): string
    {
        if ($provider !== 'auth0') {
            throw new SocialAuthException('Provedor de login social invalido.');
        }

        return $this->auth0AuthorizationUrl($state);
    }

    public function exchangeCodeForIdentity(string $provider, string $code): SocialIdentity
    {
        if ($provider !== 'auth0') {
            throw new SocialAuthException('Provedor de login social invalido.');
        }

        return $this->auth0IdentityFromCode($code);
    }

    public function resolveUser(string $provider, SocialIdentity $identity): User
    {
        if ($provider !== 'auth0') {
            throw new SocialAuthException('Provedor de login social invalido.');
        }

        $email = Str::lower(trim($identity->email));

        $user = User::query()
            ->where('auth0_id', $identity->providerId)
            ->first();

        if (!$user) {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();
        }

        if ($user) {
            $updates = [];

            if ($user->auth0_id !== $identity->providerId) {
                $updates['auth0_id'] = $identity->providerId;
            }

            if ($identity->emailVerified && $user->email_verified_at === null) {
                $updates['email_verified_at'] = now();
            }

            if (trim((string) $user->name) === '' && trim($identity->name) !== '') {
                $updates['name'] = $identity->name;
            }

            if (!$user->avatar && $identity->avatar) {
                $updates['avatar'] = $identity->avatar;
            }

            if ($updates !== []) {
                $user->fill($updates);
                $user->save();
            }

            return $user->refresh();
        }

        return User::create([
            'name' => trim($identity->name) !== '' ? $identity->name : Str::before($email, '@'),
            'email' => $email,
            'auth0_id' => $identity->providerId,
            'email_verified_at' => $identity->emailVerified ? now() : null,
            'avatar' => $identity->avatar,
            'password' => Str::random(40),
        ]);
    }

    public function issueExchangeCode(User $user, string $provider): string
    {
        if ($provider !== 'auth0') {
            throw new SocialAuthException('Provedor de login social invalido.');
        }

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
            $fragment = '#' . $rawFragment;
        }

        $query = http_build_query(array_filter(
            $parameters,
            static fn($value): bool => $value !== null && $value !== ''
        ));

        if ($query === '') {
            return $baseUrl . $fragment;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . $query . $fragment;
    }

    public function callbackUrl(string $provider): string
    {
        return route('auth.oauth.callback', ['provider' => $provider]);
    }

    private function auth0AuthorizationUrl(string $state): string
    {
        $domain = trim((string) config('services.auth0.domain', ''));
        $clientId = trim((string) config('services.auth0.client_id', ''));

        if ($domain === '' || $clientId === '') {
            throw new SocialAuthException('O login com Auth0 nao esta configurado.');
        }

        return $this->buildFrontendRedirect(
            'https://' . $domain . '/authorize',
            [
                'client_id' => $clientId,
                'redirect_uri' => $this->callbackUrl('auth0'),
                'response_type' => 'code',
                'scope' => 'openid profile email',
                'state' => $state,
            ]
        );
    }

    private function auth0IdentityFromCode(string $code): SocialIdentity
    {
        $domain = trim((string) config('services.auth0.domain', ''));
        $clientId = trim((string) config('services.auth0.client_id', ''));
        $clientSecret = trim((string) config('services.auth0.client_secret', ''));

        if ($domain === '' || $clientId === '' || $clientSecret === '') {
            throw new SocialAuthException('O login com Auth0 nao esta configurado.');
        }

        // Exchange code for tokens
        $tokenResponse = Http::asForm()
            ->acceptJson()
            ->post('https://' . $domain . '/oauth/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $this->callbackUrl('auth0'),
                'grant_type' => 'authorization_code',
            ]);

        if (!$tokenResponse->successful()) {
            throw new SocialAuthException('Nao foi possivel concluir o login com Auth0.');
        }

        $idToken = trim((string) $tokenResponse->json('id_token', ''));
        $accessToken = trim((string) $tokenResponse->json('access_token', ''));

        if ($idToken === '' || $accessToken === '') {
            throw new SocialAuthException('O Auth0 nao retornou tokens validos.');
        }

        // Decode and validate ID token
        try {
            $decodedToken = $this->decodeAndValidateIdToken($idToken, $domain, $clientId);
        } catch (\Exception $e) {
            throw new SocialAuthException('Nao foi possivel validar o token do Auth0: ' . $e->getMessage());
        }

        $providerId = trim((string) ($decodedToken['sub'] ?? ''));
        $email = Str::lower(trim((string) ($decodedToken['email'] ?? '')));
        $name = trim((string) ($decodedToken['name'] ?? ''));
        $picture = trim((string) ($decodedToken['picture'] ?? ''));
        $emailVerified = filter_var($decodedToken['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($providerId === '' || $email === '') {
            throw new SocialAuthException('Sua conta do Auth0 precisa informar um e-mail valido.');
        }

        if (!$emailVerified) {
            throw new SocialAuthException('Sua conta do Auth0 precisa ter um e-mail verificado para entrar.');
        }

        return new SocialIdentity(
            provider: 'auth0',
            providerId: $providerId,
            email: $email,
            name: $name,
            avatar: $picture !== '' ? $picture : null,
            emailVerified: true,
        );
    }

    private function decodeAndValidateIdToken(string $token, string $domain, string $clientId): array
    {
        try {
            // Get JWKS from Auth0
            $jwksResponse = Http::acceptJson()
                ->get('https://' . $domain . '/.well-known/jwks.json');

            if (!$jwksResponse->successful()) {
                throw new \Exception('Could not fetch JWKS from Auth0');
            }

            $jwks = JWK::parseKeySet((array) $jwksResponse->json());

            // Decode the token
            $decoded = JWT::decode($token, $jwks, ['RS256']);

            // Validate token claims
            if (($decoded->aud ?? null) !== $clientId) {
                throw new \Exception('Invalid audience in token');
            }

            if (($decoded->iss ?? null) !== 'https://' . $domain . '/') {
                throw new \Exception('Invalid issuer in token');
            }

            return (array) $decoded;
        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new \Exception('Token has expired: ' . $e->getMessage());
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            throw new \Exception('Invalid token signature: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception('Token validation failed: ' . $e->getMessage());
        }
    }

    private function allowedRedirectUris(): array
    {
        $allowedRedirectUris = config('services.oauth.allowed_redirect_uris', []);

        return array_values(array_filter(
            is_array($allowedRedirectUris) ? $allowedRedirectUris : [],
            static fn($value): bool => is_string($value) && trim($value) !== ''
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
        return 'social-oauth-state:' . $state;
    }

    private function exchangeCodeCacheKey(string $code): string
    {
        return 'social-oauth-exchange:' . $code;
    }
}
