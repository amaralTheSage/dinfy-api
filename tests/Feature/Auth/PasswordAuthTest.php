<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/WorkosTestHelpers.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.workos.client_id', 'client_test_123');
    config()->set('services.workos.api_key', 'sk_test_123');
});

test('it registers a user through WorkOS and returns a local API token', function () {
    bindWorkosClient([
        workosResponse(workosUsersListPayload([])),
        workosResponse(workosUserPayload(
            id: 'user_123',
            email: 'john@example.com',
            firstName: 'John',
            lastName: 'Example',
            metadata: [
                'phone' => '(11) 99999-9999',
                'phone_normalized' => '11999999999',
            ],
        )),
    ]);

    $this->postJson('/api/auth/register', [
        'name' => 'John Example',
        'email' => 'john@example.com',
        'phone' => '(11) 99999-9999',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ])
        ->assertCreated()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'phone'],
            'token',
        ])
        ->assertJsonPath('user.email', 'john@example.com')
        ->assertJsonPath('user.phone', '(11) 99999-9999');

    $user = User::query()->sole();

    expect($user->workos_user_id)->toBe('user_123');
    expect($user->phone_normalized)->toBe('11999999999');
    expect($user->email_verified_at)->not->toBeNull();
});

test('it logs in a legacy local user by phone after provisioning the account in WorkOS', function () {
    $user = User::factory()->create([
        'name' => 'Jane Legacy',
        'email' => 'jane@example.com',
        'phone' => '(11) 98888-7777',
        'phone_normalized' => '11988887777',
        'workos_user_id' => null,
    ]);

    bindWorkosClient([
        workosResponse(workosUsersListPayload([])),
        workosResponse(workosUserPayload(
            id: 'user_456',
            email: 'jane@example.com',
            firstName: 'Jane',
            lastName: 'Legacy',
            metadata: [
                'phone' => '(11) 98888-7777',
                'phone_normalized' => '11988887777',
            ],
        )),
        workosResponse(workosAuthenticatePayload(workosUserPayload(
            id: 'user_456',
            email: 'jane@example.com',
            firstName: 'Jane',
            lastName: 'Legacy',
            metadata: [
                'phone' => '(11) 98888-7777',
                'phone_normalized' => '11988887777',
            ],
        ))),
    ]);

    $this->postJson('/api/auth/login', [
        'login' => '(11) 98888-7777',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'token',
        ])
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', 'jane@example.com');

    $user->refresh();

    expect($user->workos_user_id)->toBe('user_456');
});

test('it syncs profile email and name changes to WorkOS', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'workos_user_id' => 'user_789',
        'email_verified_at' => now(),
    ]);

    Sanctum::actingAs($user);

    bindWorkosClient([
        workosResponse(workosUserPayload(
            id: 'user_789',
            email: 'old@example.com',
            firstName: 'Old',
            lastName: 'Name',
            emailVerified: true,
        )),
        workosResponse(workosUserPayload(
            id: 'user_789',
            email: 'new@example.com',
            firstName: 'New',
            lastName: 'Name',
            emailVerified: false,
        )),
    ]);

    $this->putJson('/api/me', [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('name', 'New Name')
        ->assertJsonPath('email', 'new@example.com');

    $user->refresh();

    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('it validates and stores cpf cnpj on profile update', function () {
    $user = User::factory()->create([
        'cpf_cnpj' => null,
    ]);

    Sanctum::actingAs($user);

    $this->putJson('/api/me', [
        'cpfCnpj' => '529.982.247-25',
    ])
        ->assertOk()
        ->assertJsonPath('cpf_cnpj', '52998224725');

    $user->refresh();

    expect($user->cpf_cnpj)->toBe('52998224725');

    $this->putJson('/api/me', [
        'cpfCnpj' => '111.111.111-11',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cpfCnpj');
});

test('it stores and returns the open finance address on profile update', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->putJson('/api/me', [
        'phone' => '(53) 99123-4567',
        'address' => [
            'zipcode' => '96015-420',
            'street' => 'Rua Padre Anchieta',
            'neighborhood' => 'Centro',
            'addressNumber' => '4715',
            'addressComplement' => 'Sala 2',
            'state' => 'rs',
            'city' => 'Pelotas',
        ],
    ])
        ->assertOk()
        ->assertJsonPath('phone', '(53) 99123-4567')
        ->assertJsonPath('address.zipcode', '96015420')
        ->assertJsonPath('address.addressNumber', '4715')
        ->assertJsonPath('address.state', 'RS')
        ->assertJsonPath('address.city', 'Pelotas');

    $this->assertDatabaseHas('user_addresses', [
        'user_id' => $user->id,
        'type' => 'openfinance',
        'zipcode' => '96015420',
        'address_number' => '4715',
        'state' => 'RS',
        'city' => 'Pelotas',
    ]);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('phone', '(53) 99123-4567')
        ->assertJsonPath('address.street', 'Rua Padre Anchieta');
});
