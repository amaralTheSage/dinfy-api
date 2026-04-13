<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeController extends Controller
{
    public function update(Request $request)
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
        ]);

        if (array_key_exists('phone', $validated)) {
            $phone = trim((string) ($validated['phone'] ?? ''));
            $validated['phone'] = $phone !== '' ? $phone : null;
            $validated['phone_normalized'] = PhoneNormalizer::normalize($validated['phone']);
            $this->ensurePhoneIsAvailable($validated['phone_normalized'], $user->id);
        }

        $user->fill($validated);
        $user->save();

        return response()->json($user);
    }

    public function updateWhatsApp(Request $request)
    {
        $user = $request->user();

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

        $user->fill([
            'whatsapp_phone' => $phone,
            'whatsapp_phone_normalized' => $phoneNormalized,
            'whatsapp_opted_in_at' => $consent ? now() : null,
        ]);
        $user->save();

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
        if (!$avatar) {
            return;
        }

        if (str_starts_with($avatar, '/storage/')) {
            $relative = substr($avatar, strlen('/storage/'));
            Storage::disk('public')->delete($relative);
        }
    }

    private function ensurePhoneIsAvailable(?string $phoneNormalized, int $ignoreUserId): void
    {
        if (!$phoneNormalized) {
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

    private function ensureWhatsAppPhoneIsAvailable(?string $phoneNormalized, int $ignoreUserId): void
    {
        if (!$phoneNormalized) {
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
}
