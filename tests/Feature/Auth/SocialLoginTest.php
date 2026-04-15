<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();

    config()->set('app.url', 'http://localhost');
    config()->set('services.oauth.allowed_redirect_uris', ['dinfy://auth-callback']);
    config()->set('services.oauth.fallback_redirect_uri', 'dinfy://auth-callback');
    config()->set('services.google.client_id', 'google-client-id');
    config()->set('services.google.client_secret', 'google-client-secret');
    config()->set('services.facebook.app_id', 'facebook-app-id');
    config()->set('services.facebook.app_secret', 'facebook-app-secret');
    config()->set('services.facebook.graph_version', 'v22.0');
});

test('google social login creates a user and exchanges the oauth code', function () {
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'google-access-token',
            'token_type' => 'Bearer',
        ], 200),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
            'sub' => 'google-user-123',
            'email' => 'john@example.com',
            'email_verified' => true,
            'name' => 'John Google',
            'picture' => 'https://example.com/google-avatar.png',
        ], 200),
    ]);

    $redirectResponse = $this->get('/api/auth/oauth/google/redirect?redirect_uri='.urlencode('dinfy://auth-callback'));
    $redirectResponse->assertRedirect();

    $state = oauthQueryParameter($redirectResponse->headers->get('Location'), 'state');
    expect($state)->not->toBeNull();

    $callbackResponse = $this->get('/api/auth/oauth/google/callback?state='.urlencode((string) $state).'&code=google-code');
    $callbackResponse->assertRedirect();

    $callbackLocation = $callbackResponse->headers->get('Location');
    $exchangeCode = oauthQueryParameter($callbackLocation, 'oauth_code');

    expect(oauthQueryParameter($callbackLocation, 'provider'))->toBe('google');
    expect($exchangeCode)->not->toBeNull();

    $exchangeResponse = $this->postJson('/api/auth/oauth/exchange', [
        'code' => $exchangeCode,
    ]);

    $exchangeResponse
        ->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'token',
        ])
        ->assertJsonPath('user.email', 'john@example.com')
        ->assertJsonPath('user.name', 'John Google');

    $user = User::query()->sole();

    expect($user->google_id)->toBe('google-user-123');
    expect($user->avatar)->toBe('https://example.com/google-avatar.png');
    expect($user->email_verified_at)->not->toBeNull();

    $this->postJson('/api/auth/oauth/exchange', [
        'code' => $exchangeCode,
    ])->assertUnprocessable()->assertJsonValidationErrors(['code']);
});

test('facebook social login links an existing account by email', function () {
    $user = User::factory()->create([
        'name' => 'Jane Existing',
        'email' => 'jane@example.com',
        'email_verified_at' => null,
        'facebook_id' => null,
    ]);

    Http::fake([
        'https://graph.facebook.com/v22.0/oauth/access_token*' => Http::response([
            'access_token' => 'facebook-access-token',
            'token_type' => 'bearer',
        ], 200),
        'https://graph.facebook.com/v22.0/debug_token*' => Http::response([
            'data' => [
                'app_id' => 'facebook-app-id',
                'is_valid' => true,
            ],
        ], 200),
        'https://graph.facebook.com/v22.0/me*' => Http::response([
            'id' => 'facebook-user-456',
            'name' => 'Jane Facebook',
            'email' => 'jane@example.com',
            'picture' => [
                'data' => [
                    'url' => 'https://example.com/facebook-avatar.png',
                ],
            ],
        ], 200),
    ]);

    $redirectResponse = $this->get('/api/auth/oauth/facebook/redirect?redirect_uri='.urlencode('dinfy://auth-callback'));
    $redirectResponse->assertRedirect();

    $state = oauthQueryParameter($redirectResponse->headers->get('Location'), 'state');
    expect($state)->not->toBeNull();

    $callbackResponse = $this->get('/api/auth/oauth/facebook/callback?state='.urlencode((string) $state).'&code=facebook-code');
    $callbackResponse->assertRedirect();

    $callbackLocation = $callbackResponse->headers->get('Location');
    $exchangeCode = oauthQueryParameter($callbackLocation, 'oauth_code');

    expect(oauthQueryParameter($callbackLocation, 'provider'))->toBe('facebook');
    expect($exchangeCode)->not->toBeNull();

    $this->postJson('/api/auth/oauth/exchange', [
        'code' => $exchangeCode,
    ])
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', 'jane@example.com');

    $user->refresh();

    expect(User::query()->count())->toBe(1);
    expect($user->facebook_id)->toBe('facebook-user-456');
    expect($user->avatar)->toBe('https://example.com/facebook-avatar.png');
    expect($user->email_verified_at)->not->toBeNull();
});

function oauthQueryParameter(?string $url, string $parameter): ?string
{
    if ($url === null || trim($url) === '') {
        return null;
    }

    $query = parse_url($url, PHP_URL_QUERY);
    if (!is_string($query) || $query === '') {
        return null;
    }

    parse_str($query, $parameters);

    $value = $parameters[$parameter] ?? null;

    return is_string($value) && trim($value) !== '' ? $value : null;
}
