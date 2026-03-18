<?php

use App\Models\User;
use App\Notifications\PasswordResetTokenNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

test('it sends password reset email for existing users', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'john@example.com',
    ]);

    $this->postJson('/api/auth/forgot-password', [
        'email' => $user->email,
    ])
        ->assertOk()
        ->assertJsonStructure(['message']);

    Notification::assertSentTo($user, PasswordResetTokenNotification::class);
});

test('it resets password with a valid token', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
    ]);

    $token = Password::broker()->createToken($user);

    $this->postJson('/api/auth/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])
        ->assertOk()
        ->assertJsonStructure(['message']);

    $user->refresh();

    expect(Hash::check('new-password-123', $user->password))->toBeTrue();
});
