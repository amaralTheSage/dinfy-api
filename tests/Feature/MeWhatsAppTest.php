<?php

use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('assistant.phone.default_country_code', '55');
    config()->set('services.n8n.whatsapp_opt_in_url', 'https://n8n.dinfy.app/webhook/opt-in');
});

it('sends the whatsapp opt-in webhook when consent is activated', function () {
    Http::fake([
        'https://n8n.dinfy.app/webhook/opt-in' => Http::response(['ok' => true]),
    ]);

    $user = User::factory()->create([
        'name' => 'Gabriel',
        'email' => 'gabriel@example.com',
    ]);

    Sanctum::actingAs($user);

    $response = $this->putJson('/api/me/whatsapp', [
        'whatsapp_phone' => '(11) 99999-9999',
        'consent' => true,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('whatsapp_phone', '(11) 99999-9999');

    Http::assertSent(function (HttpRequest $request) use ($user): bool {
        return $request->url() === 'https://n8n.dinfy.app/webhook/opt-in'
            && $request['userId'] === $user->id
            && $request['name'] === 'Gabriel'
            && $request['email'] === 'gabriel@example.com'
            && $request['phone'] === '(11) 99999-9999'
            && $request['phoneNormalized'] === PhoneNormalizer::normalize('(11) 99999-9999')
            && $request['consent'] === true
            && $request['channel'] === 'whatsapp'
            && filled($request['consentAt']);
    });
});

it('does not send the whatsapp opt-in webhook when consent is disabled', function () {
    Http::fake();

    $user = User::factory()->create([
        'whatsapp_phone' => '(11) 99999-9999',
        'whatsapp_phone_normalized' => PhoneNormalizer::normalize('(11) 99999-9999'),
        'whatsapp_opted_in_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->putJson('/api/me/whatsapp', [
        'whatsapp_phone' => '(11) 99999-9999',
        'consent' => false,
    ]);

    $response->assertOk();

    Http::assertNothingSent();
});

it('does not resend the whatsapp opt-in webhook when the user saves the same opted-in number', function () {
    Http::fake();

    $user = User::factory()->create([
        'whatsapp_phone' => '(11) 99999-9999',
        'whatsapp_phone_normalized' => PhoneNormalizer::normalize('(11) 99999-9999'),
        'whatsapp_opted_in_at' => now(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->putJson('/api/me/whatsapp', [
        'whatsapp_phone' => '(11) 99999-9999',
        'consent' => true,
    ]);

    $response->assertOk();

    Http::assertNothingSent();
});
