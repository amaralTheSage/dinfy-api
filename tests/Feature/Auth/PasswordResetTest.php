<?php

use App\Models\User;
use App\Notifications\PasswordResetTokenNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/WorkosTestHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.workos.client_id', 'client_test_123');
    config()->set('services.workos.api_key', 'sk_test_123');
});

test('it sends a WorkOS password reset email for an existing user', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'john@example.com',
        'workos_user_id' => null,
    ]);

    bindWorkosClient([
        workosResponse(workosUsersListPayload([])),
        workosResponse(workosUserPayload(
            id: 'user_123',
            email: 'john@example.com',
            firstName: 'John',
            lastName: 'Example',
        )),
        workosResponse(workosPasswordResetPayload(
            userId: 'user_123',
            email: 'john@example.com',
        )),
    ]);

    $this->postJson('/api/auth/forgot-password', [
        'email' => $user->email,
    ])
        ->assertOk()
        ->assertJsonStructure(['message']);

    $user->refresh();

    expect($user->workos_user_id)->toBe('user_123');

    Notification::assertSentTo($user, PasswordResetTokenNotification::class);
});

test('it resets the password with a valid WorkOS token', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'workos_user_id' => 'user_123',
    ]);

    $token = $user->createToken('existing-session')->plainTextToken;
    expect($token)->not->toBe('');

    bindWorkosClient([
        workosResponse([
            'user' => workosUserPayload(
                id: 'user_123',
                email: 'john@example.com',
                firstName: 'John',
                lastName: 'Example',
                emailVerified: true,
            ),
        ]),
    ]);

    $this->postJson('/api/auth/reset-password', [
        'email' => $user->email,
        'token' => 'reset-token-123',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Senha redefinida com sucesso.');

    $user->refresh();

    expect($user->tokens()->count())->toBe(0);
    expect($user->email_verified_at)->not->toBeNull();
});
