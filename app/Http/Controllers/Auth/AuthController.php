<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        Log::info('Register atingido');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $phone = isset($validated['phone']) ? trim((string) $validated['phone']) : null;
        $phone = $phone !== '' ? $phone : null;
        $phoneNormalized = PhoneNormalizer::normalize($phone);

        $this->ensurePhoneIsAvailable($phoneNormalized);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $phone,
            'phone_normalized' => $phoneNormalized,
            'password' => $validated['password'],
        ]);

        $tokenName = $request->userAgent() ?: 'auth-token';
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = $validated['login'];
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        /** @var User|null $user */
        $user = $field === 'email'
            ? User::query()->where('email', $login)->first()
            : User::query()
                ->where(function ($query) use ($login): void {
                    $query->where('phone', $login);

                    $variants = PhoneNormalizer::variants($login);
                    if ($variants !== []) {
                        $query->orWhereIn('phone_normalized', $variants);
                    }
                })
                ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        $tokenName = $request->userAgent() ?: 'auth-token';
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'reset_url' => ['nullable', 'string', 'max:2048'],
        ]);

        if (!empty($validated['reset_url'])) {
            $parsed = parse_url((string) $validated['reset_url']);
            $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
            if ($scheme === '' || !in_array($scheme, ['http', 'https', 'dinfy'], true)) {
                throw ValidationException::withMessages([
                    'reset_url' => ['The reset url scheme must be http, https or dinfy.'],
                ]);
            }
        }

        /** @var User|null $user */
        $user = User::query()
            ->where('email', $validated['email'])
            ->first();

        if ($user) {
            $token = Password::broker()->createToken($user);
            $user->sendPasswordResetNotification($token, $validated['reset_url'] ?? null);
        }

        return response()->json([
            'message' => 'Se o e-mail existir na nossa base, você receberá as instruções de recuperação em instantes.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'token' => $validated['token'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Senha redefinida com sucesso.',
            ]);
        }

        $message = match ($status) {
            Password::INVALID_TOKEN => 'Token inválido ou expirado.',
            Password::INVALID_USER => 'Não encontramos um usuário com esse e-mail.',
            default => 'Não foi possível redefinir a senha.',
        };

        throw ValidationException::withMessages([
            'email' => [$message],
        ]);
    }

    private function ensurePhoneIsAvailable(?string $phoneNormalized, ?int $ignoreUserId = null): void
    {
        if (!$phoneNormalized) {
            return;
        }

        $query = User::query()->where('phone_normalized', $phoneNormalized);

        if ($ignoreUserId !== null) {
            $query->whereKeyNot($ignoreUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['Este telefone já está em uso.'],
            ]);
        }
    }
}
