<?php

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as Psr7Response;
use WorkOS\WorkOS;

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

function workosUserPayload(
    string $id,
    string $email,
    string $firstName,
    string $lastName,
    ?string $picture = null,
    bool $emailVerified = true,
    ?array $metadata = null,
): array {
    return [
        'object' => 'user',
        'id' => $id,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'profile_picture_url' => $picture,
        'email' => $email,
        'email_verified' => $emailVerified,
        'external_id' => null,
        'last_sign_in_at' => '2026-04-15T19:00:00.000Z',
        'created_at' => '2026-04-15T19:00:00.000Z',
        'updated_at' => '2026-04-15T19:00:00.000Z',
        'metadata' => $metadata ?? [],
        'locale' => 'pt-BR',
    ];
}

function workosAuthenticatePayload(
    array $user,
    string $accessToken = 'workos-access-token',
    string $refreshToken = 'workos-refresh-token',
): array {
    return [
        'user' => $user,
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'organization_id' => null,
    ];
}

function workosUsersListPayload(array $users): array
{
    return [
        'data' => $users,
        'list_metadata' => [
            'before' => null,
            'after' => null,
        ],
    ];
}

function workosPasswordResetPayload(
    string $userId,
    string $email,
    string $token = 'reset-token-123',
): array {
    return [
        'object' => 'password_reset',
        'id' => 'pwd_reset_123',
        'user_id' => $userId,
        'email' => $email,
        'expires_at' => '2026-04-16T20:00:00.000Z',
        'created_at' => '2026-04-16T19:00:00.000Z',
        'password_reset_token' => $token,
        'password_reset_url' => "https://dinfy.app/reset-password?token={$token}&email=".rawurlencode($email),
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
