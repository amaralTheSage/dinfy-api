<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\WorkosAuthService;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, WorkosAuthService $workosAuth)
    {
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

        try {
            $user = $workosAuth->registerWithPassword(
                name: $validated['name'],
                email: $validated['email'],
                password: $validated['password'],
                phone: $phone,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\RuntimeException $exception) {
            Log::warning('WorkOS register failed.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        } catch (\Throwable $exception) {
            Log::error('Unexpected WorkOS register failure.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Nao foi possivel criar a conta agora.',
            ], 503);
        }

        return $this->authenticatedResponse($request, $user, 201);
    }

    public function login(Request $request, WorkosAuthService $workosAuth)
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        try {
            $user = $workosAuth->authenticateWithPassword(
                login: $validated['login'],
                password: $validated['password'],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\RuntimeException $exception) {
            Log::warning('WorkOS login failed.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        } catch (\Throwable $exception) {
            Log::error('Unexpected WorkOS login failure.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Nao foi possivel concluir o login agora.',
            ], 503);
        }

        return $this->authenticatedResponse($request, $user);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    public function forgotPassword(Request $request, WorkosAuthService $workosAuth)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'reset_url' => ['nullable', 'string', 'max:2048'],
        ]);

        if (! empty($validated['reset_url'])) {
            $parsed = parse_url((string) $validated['reset_url']);
            $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
            if ($scheme === '' || ! in_array($scheme, ['http', 'https', 'dinfy'], true)) {
                throw ValidationException::withMessages([
                    'reset_url' => ['The reset url scheme must be http, https or dinfy.'],
                ]);
            }
        }

        try {
            $workosAuth->sendPasswordReset(
                email: $validated['email'],
                resetUrl: $validated['reset_url'] ?? null,
            );
        } catch (\RuntimeException $exception) {
            Log::warning('WorkOS password reset request failed.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        } catch (\Throwable $exception) {
            Log::error('Unexpected WorkOS password reset request failure.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Nao foi possivel iniciar a recuperacao de senha agora.',
            ], 503);
        }

        return response()->json([
            'message' => 'Se o e-mail existir na nossa base, voce recebera as instrucoes de recuperacao em instantes.',
        ]);
    }

    public function resetPassword(Request $request, WorkosAuthService $workosAuth)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $workosAuth->confirmPasswordReset(
                email: $validated['email'],
                token: $validated['token'],
                newPassword: $validated['password'],
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\RuntimeException $exception) {
            Log::warning('WorkOS password reset confirmation failed.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        } catch (\Throwable $exception) {
            Log::error('Unexpected WorkOS password reset confirmation failure.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Nao foi possivel redefinir a senha agora.',
            ], 503);
        }

        return response()->json([
            'message' => 'Senha redefinida com sucesso.',
        ]);
    }

    private function ensurePhoneIsAvailable(?string $phoneNormalized, ?int $ignoreUserId = null): void
    {
        if (! $phoneNormalized) {
            return;
        }

        $query = User::query()->where(function ($builder) use ($phoneNormalized): void {
            $builder->where('phone_normalized', $phoneNormalized)
                ->orWhere('whatsapp_phone_normalized', $phoneNormalized);
        });

        if ($ignoreUserId !== null) {
            $query->whereKeyNot($ignoreUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['Este telefone ja esta em uso.'],
            ]);
        }
    }

    private function authenticatedResponse(Request $request, User $user, int $status = 200)
    {
        $tokenName = $request->userAgent() ?: 'auth-token';
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], $status);
    }
}
