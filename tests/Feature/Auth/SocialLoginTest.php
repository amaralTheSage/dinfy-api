<?php

use App\Models\User;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use WorkOS\WorkOS;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Http::preventStrayRequests();

    config()->set('app.url', 'http://localhost');
    config()->set('services.oauth.allowed_redirect_uris', ['dinfy://auth-callback']);
    config()->set('services.oauth.fallback_redirect_uri', 'dinfy://auth-callback');
    config()->set('services.workos.client_id', 'client_test_123');
    config()->set('services.workos.api_key', 'sk_test_123');
});

test('workos social login creates a user and exchanges the oauth code', function () {
    bindWorkosClient([
        workosResponse(workosAuthenticatePayload(
            id: 'user_123',
            email: 'john@example.com',
            firstName: 'John',
            lastName: 'WorkOS',
            picture: 'https://example.com/workos-avatar.png',
        )),
    ]);

    $redirectResponse = $this->get('/api/auth/oauth/workos/redirect?redirect_uri='.urlencode('dinfy://auth-callback'));
    $redirectResponse->assertRedirect();

    $authorizationLocation = $redirectResponse->headers->get('Location');
    $state = oauthQueryParameter($authorizationLocation, 'state');

    expect($authorizationLocation)->not->toBeNull();
    expect((string) $authorizationLocation)->toContain('user_management/authorize');
    expect(oauthQueryParameter($authorizationLocation, 'client_id'))->toBe('client_test_123');
    expect(oauthQueryParameter($authorizationLocation, 'provider'))->toBe('authkit');
    expect($state)->not->toBeNull();

    $callbackResponse = $this->get('/api/auth/oauth/workos/callback?state='.urlencode((string) $state).'&code=workos-code');
    $callbackResponse->assertRedirect();

    $callbackLocation = $callbackResponse->headers->get('Location');
    $exchangeCode = oauthQueryParameter($callbackLocation, 'oauth_code');

    expect(oauthQueryParameter($callbackLocation, 'provider'))->toBe('workos');
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
        ->assertJsonPath('user.name', 'John WorkOS');

    $user = User::query()->sole();

    expect($user->workos_user_id)->toBe('user_123');
    expect($user->avatar)->toBe('https://example.com/workos-avatar.png');
    expect($user->email_verified_at)->not->toBeNull();

    $this->postJson('/api/auth/oauth/exchange', [
        'code' => $exchangeCode,
    ])->assertUnprocessable()->assertJsonValidationErrors(['code']);
});

test('workos social login links an existing account by email', function () {
    $user = User::factory()->create([
        'name' => 'Jane Existing',
        'email' => 'jane@example.com',
        'email_verified_at' => null,
        'workos_user_id' => null,
        'avatar' => null,
    ]);

    bindWorkosClient([
        workosResponse(workosAuthenticatePayload(
            id: 'user_456',
            email: 'jane@example.com',
            firstName: 'Jane',
            lastName: 'Linked',
            picture: 'https://example.com/workos-linked-avatar.png',
        )),
    ]);

    $redirectResponse = $this->get('/api/auth/oauth/workos/redirect?redirect_uri='.urlencode('dinfy://auth-callback'));
    $redirectResponse->assertRedirect();

    $state = oauthQueryParameter($redirectResponse->headers->get('Location'), 'state');
    expect($state)->not->toBeNull();

    $callbackResponse = $this->get('/api/auth/oauth/workos/callback?state='.urlencode((string) $state).'&code=workos-code');
    $callbackResponse->assertRedirect();

    $callbackLocation = $callbackResponse->headers->get('Location');
    $exchangeCode = oauthQueryParameter($callbackLocation, 'oauth_code');

    expect(oauthQueryParameter($callbackLocation, 'provider'))->toBe('workos');
    expect($exchangeCode)->not->toBeNull();

    $this->postJson('/api/auth/oauth/exchange', [
        'code' => $exchangeCode,
    ])
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', 'jane@example.com');

    $user->refresh();

    expect(User::query()->count())->toBe(1);
    expect($user->workos_user_id)->toBe('user_456');
    expect($user->avatar)->toBe('https://example.com/workos-linked-avatar.png');
    expect($user->email_verified_at)->not->toBeNull();
});

test('workos redirect falls back to the configured app callback when no redirect uri is provided', function () {
    bindWorkosClient([
        workosResponse(workosAuthenticatePayload(
            id: 'user_789',
            email: 'fallback@example.com',
            firstName: 'Fallback',
            lastName: 'User',
        )),
    ]);

    $redirectResponse = $this->get('/api/auth/oauth/workos/redirect');
    $redirectResponse->assertRedirect();

    $authorizationLocation = $redirectResponse->headers->get('Location');
    $state = oauthQueryParameter($authorizationLocation, 'state');

    expect($state)->not->toBeNull();
    expect(oauthQueryParameter($authorizationLocation, 'redirect_uri'))
        ->toBe(route('auth.oauth.callback', ['provider' => 'workos']));

    $callbackResponse = $this->get('/api/auth/oauth/workos/callback?state='.urlencode((string) $state).'&code=workos-code');
    $callbackResponse->assertRedirect();

    $callbackLocation = $callbackResponse->headers->get('Location');

    expect($callbackLocation)->toStartWith('dinfy://auth-callback');
    expect(oauthQueryParameter($callbackLocation, 'provider'))->toBe('workos');
    expect(oauthQueryParameter($callbackLocation, 'oauth_code'))->not->toBeNull();
});

function bindWorkosClient(array $responses): void
{
    $mockHandler = new MockHandler($responses);
    $handler = HandlerStack::create($mockHandler);

    app()->instance(WorkOS::class, new WorkOS(
        apiKey: (string) config('services.workos.api_key'),
        clientId: (string) config('services.workos.client_id'),
        handler: $handler,
    ));
}

function workosResponse(array $payload, int $status = 200): Psr7Response
{
    return new Psr7Response(
        $status,
        ['Content-Type' => 'application/json'],
        json_encode($payload, JSON_THROW_ON_ERROR),
    );
}

function workosAuthenticatePayload(
    string $id,
    string $email,
    string $firstName,
    string $lastName,
    ?string $picture = null,
): array {
    return [
        'user' => [
            'object' => 'user',
            'id' => $id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'profile_picture_url' => $picture,
            'email' => $email,
            'email_verified' => true,
            'external_id' => null,
            'last_sign_in_at' => '2026-04-15T19:00:00.000Z',
            'created_at' => '2026-04-15T19:00:00.000Z',
            'updated_at' => '2026-04-15T19:00:00.000Z',
            'metadata' => [],
            'locale' => 'pt-BR',
        ],
        'access_token' => 'workos-access-token',
        'refresh_token' => 'workos-refresh-token',
        'organization_id' => null,
    ];
}

function oauthQueryParameter(?string $url, string $parameter): ?string
{
    if ($url === null || trim($url) === '') {
        return null;
    }

    $query = parse_url($url, PHP_URL_QUERY);
    if (! is_string($query) || $query === '') {
        return null;
    }

    parse_str($query, $parameters);

    $value = $parameters[$parameter] ?? null;

    return is_string($value) && trim($value) !== '' ? $value : null;
}
