<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserAddress;
use App\Services\Auth\WorkosAuthService;
use App\Support\BrazilDocument;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeController extends Controller
{
    private const ADDRESS_TYPE = 'openfinance';

    public function show(Request $request)
    {
        return response()->json($this->userForResponse($request->user()));
    }

    public function update(Request $request, WorkosAuthService $workosAuth)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],
            'cpfCnpj' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && trim((string) $value) !== '' && ! BrazilDocument::isValid((string) $value)) {
                        $fail('Informe um CPF/CNPJ valido.');
                    }
                },
            ],
            'address' => ['sometimes', 'nullable', 'array'],
            'address.zipcode' => ['required_with:address', 'string', 'max:20'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.neighborhood' => ['required_with:address', 'string', 'max:255'],
            'address.addressNumber' => ['required_with:address', 'string', 'max:30'],
            'address.addressComplement' => ['nullable', 'string', 'max:255'],
            'address.state' => ['required_with:address', 'string', 'size:2'],
            'address.city' => ['required_with:address', 'string', 'max:255'],
        ]);

        if (array_key_exists('phone', $validated)) {
            $phone = trim((string) ($validated['phone'] ?? ''));
            $validated['phone'] = $phone !== '' ? $phone : null;
            $validated['phone_normalized'] = PhoneNormalizer::normalize($validated['phone']);
            $this->ensurePhoneIsAvailable($validated['phone_normalized'], $user->id);
        }

        if (array_key_exists('cpfCnpj', $validated)) {
            $validated['cpf_cnpj'] = BrazilDocument::normalize($validated['cpfCnpj']);
            $this->ensureCpfCnpjIsAvailable($validated['cpf_cnpj'], $user->id);
        }

        $workosAttributes = array_filter([
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
        ], static fn ($value): bool => $value !== null);

        if ($workosAttributes !== []) {
            try {
                $user = $workosAuth->syncProfile($user, $workosAttributes);
            } catch (\RuntimeException $exception) {
                Log::warning('WorkOS profile sync failed.', [
                    'user_id' => $user->id,
                    'message' => $exception->getMessage(),
                ]);

                return response()->json([
                    'message' => $exception->getMessage(),
                ], 503);
            }
        }

        if (array_key_exists('phone', $validated)) {
            $user->fill([
                'phone' => $validated['phone'],
                'phone_normalized' => $validated['phone_normalized'],
            ]);
        }
        if (array_key_exists('cpf_cnpj', $validated)) {
            $user->cpf_cnpj = $validated['cpf_cnpj'];
        }

        $user->save();

        if (array_key_exists('address', $validated) && is_array($validated['address'])) {
            $this->storeOpenFinanceAddress($user, $validated['address']);
        }

        return response()->json($this->userForResponse($user->refresh()));
    }

    public function updateWhatsApp(Request $request)
    {
        $user = $request->user();
        $wasOptedIn = $user->whatsapp_opted_in_at !== null;
        $previousPhoneNormalized = $user->whatsapp_phone_normalized;

        $validated = $request->validate([
            'whatsapp_phone' => ['nullable', 'string', 'max:30'],
            'consent' => ['required', 'boolean'],
        ]);

        $phone = trim((string) ($validated['whatsapp_phone'] ?? ''));
        $phone = $phone !== '' ? $phone : null;
        $phoneNormalized = PhoneNormalizer::normalize($phone);
        $consent = (bool) $validated['consent'];

        if ($phone !== null && $phoneNormalized === null) {
            throw ValidationException::withMessages([
                'whatsapp_phone' => ['Informe um número de WhatsApp válido.'],
            ]);
        }

        if ($consent && $phoneNormalized === null) {
            throw ValidationException::withMessages([
                'whatsapp_phone' => ['Informe um número de WhatsApp para receber mensagens.'],
            ]);
        }

        $this->ensureWhatsAppPhoneIsAvailable($phoneNormalized, $user->id);

        $optedInAt = $consent ? now() : null;
        $user->fill([
            'whatsapp_phone' => $phone,
            'whatsapp_phone_normalized' => $phoneNormalized,
            'whatsapp_opted_in_at' => $optedInAt,
        ]);
        $user->save();

        if ($consent && (! $wasOptedIn || $previousPhoneNormalized !== $phoneNormalized)) {
            $this->sendWhatsAppOptInWebhook($user, $phone, $phoneNormalized, $optedInAt);
        }

        return response()->json($user);
    }

    public function uploadAvatar(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $this->deleteAvatarFileIfPresent($user->avatar);

        $path = $validated['avatar']->storePublicly('avatars', 'public');
        $user->avatar = '/storage/'.$path;
        $user->save();

        return response()->json($user);
    }

    public function deleteAvatar(Request $request)
    {
        $user = $request->user();

        $this->deleteAvatarFileIfPresent($user->avatar);

        $user->avatar = null;
        $user->save();

        return response()->json($user);
    }

    private function deleteAvatarFileIfPresent(?string $avatar): void
    {
        if (! $avatar) {
            return;
        }

        if (str_starts_with($avatar, '/storage/')) {
            $relative = substr($avatar, strlen('/storage/'));
            Storage::disk('public')->delete($relative);
        }
    }

    private function ensurePhoneIsAvailable(?string $phoneNormalized, int $ignoreUserId): void
    {
        if (! $phoneNormalized) {
            return;
        }

        $exists = User::query()
            ->whereKeyNot($ignoreUserId)
            ->where(function ($query) use ($phoneNormalized): void {
                $query->where('phone_normalized', $phoneNormalized)
                    ->orWhere('whatsapp_phone_normalized', $phoneNormalized);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'phone' => ['Este telefone já está em uso.'],
            ]);
        }
    }

    private function ensureCpfCnpjIsAvailable(?string $cpfCnpj, int $ignoreUserId): void
    {
        if (! $cpfCnpj) {
            return;
        }

        $exists = User::query()
            ->whereKeyNot($ignoreUserId)
            ->where('cpf_cnpj', $cpfCnpj)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'cpfCnpj' => ['Este CPF/CNPJ ja esta em uso.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function storeOpenFinanceAddress(User $user, array $address): void
    {
        UserAddress::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'type' => self::ADDRESS_TYPE,
            ],
            [
                'zipcode' => preg_replace('/\D+/', '', (string) $address['zipcode']),
                'street' => $this->nullableTrim($address['street'] ?? null),
                'neighborhood' => trim((string) $address['neighborhood']),
                'address_number' => trim((string) $address['addressNumber']),
                'address_complement' => $this->nullableTrim($address['addressComplement'] ?? null),
                'state' => strtoupper(trim((string) $address['state'])),
                'city' => trim((string) $address['city']),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function userForResponse(User $user): array
    {
        $payload = $user->toArray();
        $payload['phone'] = $user->phone ?: $user->phone_normalized;
        $payload['address'] = $this->addressForResponse($user->openFinanceAddress()->first());

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function addressForResponse(?UserAddress $address): ?array
    {
        if ($address === null) {
            return null;
        }

        return [
            'zipcode' => $address->zipcode,
            'street' => $address->street,
            'neighborhood' => $address->neighborhood,
            'addressNumber' => $address->address_number,
            'addressComplement' => $address->address_complement,
            'state' => $address->state,
            'city' => $address->city,
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function ensureWhatsAppPhoneIsAvailable(?string $phoneNormalized, int $ignoreUserId): void
    {
        if (! $phoneNormalized) {
            return;
        }

        $exists = User::query()
            ->whereKeyNot($ignoreUserId)
            ->where(function ($query) use ($phoneNormalized): void {
                $query->where('whatsapp_phone_normalized', $phoneNormalized)
                    ->orWhere('phone_normalized', $phoneNormalized);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'whatsapp_phone' => ['Este número de WhatsApp já está em uso.'],
            ]);
        }
    }

    private function sendWhatsAppOptInWebhook(
        User $user,
        ?string $phone,
        ?string $phoneNormalized,
        ?Carbon $optedInAt,
    ): void {
        if ($phone === null || $phoneNormalized === null || $optedInAt === null) {
            return;
        }

        $url = trim((string) config('services.n8n.whatsapp_opt_in_url', ''));
        if ($url === '') {
            return;
        }

        try {
            Http::acceptJson()
                ->timeout(10)
                ->post($url, [
                    'userId' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $phone,
                    'phoneNormalized' => $phoneNormalized,
                    'consent' => true,
                    'consentAt' => $optedInAt->toIso8601String(),
                    'channel' => 'whatsapp',
                ])
                ->throw();
        } catch (\Throwable $exception) {
            Log::warning('Failed to send WhatsApp opt-in webhook.', [
                'user_id' => $user->id,
                'phone_normalized' => $phoneNormalized,
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
