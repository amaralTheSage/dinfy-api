<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use WorkOS\Exception\AuthenticationException;
use WorkOS\Exception\BadRequestException;
use WorkOS\Exception\ConfigurationException;
use WorkOS\Exception\ConflictException;
use WorkOS\Exception\NotFoundException;
use WorkOS\Exception\ServerException;
use WorkOS\Exception\TimeoutException;
use WorkOS\Exception\UnprocessableEntityException;
use WorkOS\Resource\CreateUserPasswordHashType;
use WorkOS\Resource\User as WorkosUser;
use WorkOS\WorkOS;

class WorkosAuthService
{
    public function __construct(
        private readonly WorkOS $workos,
    ) {}

    public function registerWithPassword(
        string $name,
        string $email,
        string $password,
        ?string $phone = null,
    ): User {
        $normalizedName = trim($name);
        $normalizedEmail = Str::lower(trim($email));
        $normalizedPhone = $this->normalizeNullableString($phone);

        $this->ensureWorkosConfigured('O cadastro com WorkOS nao esta configurado.');

        try {
            $existingWorkosUser = $this->findWorkosUserByEmail($normalizedEmail);
            if ($existingWorkosUser) {
                throw ValidationException::withMessages([
                    'email' => ['Este e-mail ja esta em uso.'],
                ]);
            }

            $workosUser = $this->workos->userManagement()->createUser(
                email: $normalizedEmail,
                password: $password,
                firstName: $this->firstNameFromDisplayName($normalizedName),
                lastName: $this->lastNameFromDisplayName($normalizedName),
                emailVerified: true,
                metadata: $this->metadataFromValues(
                    phone: $normalizedPhone,
                ),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (ConflictException $exception) {
            throw ValidationException::withMessages([
                'email' => ['Este e-mail ja esta em uso.'],
            ]);
        } catch (BadRequestException|UnprocessableEntityException $exception) {
            throw ValidationException::withMessages([
                $this->inputFieldForWorkosException($exception->getMessage(), 'email') => [
                    $this->messageFromWorkosException($exception->getMessage(), 'Nao foi possivel validar os dados informados.'),
                ],
            ]);
        } catch (ConfigurationException $exception) {
            throw $this->workosUnavailable(
                'O cadastro com WorkOS nao esta configurado.',
                $exception,
            );
        } catch (ServerException|TimeoutException|\Throwable $exception) {
            throw $this->workosUnavailable(
                'Nao foi possivel criar a conta com WorkOS agora.',
                $exception,
            );
        }

        return $this->syncLocalUserFromWorkosUser($workosUser, [
            'name' => $normalizedName,
            'phone' => $normalizedPhone,
            'phone_normalized' => $this->normalizePhone($normalizedPhone),
            'email_verified_at' => now(),
        ]);
    }

    public function authenticateWithPassword(
        string $login,
        string $password,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): User {
        $normalizedLogin = trim($login);
        $email = $this->resolveLoginEmail($normalizedLogin);

        $this->ensureWorkosConfigured('O login com WorkOS nao esta configurado.');

        try {
            $response = $this->workos->userManagement()->authenticateWithPassword(
                email: $email,
                password: $password,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        } catch (AuthenticationException|BadRequestException|NotFoundException|UnprocessableEntityException $exception) {
            throw ValidationException::withMessages([
                'login' => ['O login ou a senha estao incorretos.'],
            ]);
        } catch (ConfigurationException $exception) {
            throw $this->workosUnavailable(
                'O login com WorkOS nao esta configurado.',
                $exception,
            );
        } catch (ServerException|TimeoutException|\Throwable $exception) {
            throw $this->workosUnavailable(
                'Nao foi possivel concluir o login com WorkOS agora.',
                $exception,
            );
        }

        return $this->syncLocalUserFromWorkosUser($response->user);
    }

    public function sendPasswordReset(string $email, ?string $resetUrl = null): void
    {
        $normalizedEmail = Str::lower(trim($email));

        $this->ensureWorkosConfigured('A recuperacao de senha com WorkOS nao esta configurada.');

        try {
            $localUser = $this->findLocalUserByEmail($normalizedEmail);

            if ($localUser) {
                $localUser = $this->provisionWorkosUserForLocalUser($localUser);
            } else {
                $existingWorkosUser = $this->findWorkosUserByEmail($normalizedEmail);
                if ($existingWorkosUser) {
                    $localUser = $this->syncLocalUserFromWorkosUser($existingWorkosUser);
                }
            }

            if (! $localUser) {
                return;
            }

            $passwordReset = $this->workos->userManagement()->resetPassword($normalizedEmail);

            $localUser->sendPasswordResetNotification(
                $passwordReset->passwordResetToken,
                $resetUrl,
                $passwordReset->expiresAt,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (NotFoundException|AuthenticationException|BadRequestException|UnprocessableEntityException) {
            // Mirror the old flow and never reveal whether the account exists.
            return;
        } catch (ConfigurationException $exception) {
            throw $this->workosUnavailable(
                'A recuperacao de senha com WorkOS nao esta configurada.',
                $exception,
            );
        } catch (ServerException|TimeoutException|\Throwable $exception) {
            throw $this->workosUnavailable(
                'Nao foi possivel iniciar a recuperacao de senha agora.',
                $exception,
            );
        }
    }

    public function confirmPasswordReset(
        string $email,
        string $token,
        string $newPassword,
    ): User {
        $normalizedEmail = Str::lower(trim($email));

        $this->ensureWorkosConfigured('A redefinicao de senha com WorkOS nao esta configurada.');

        try {
            $response = $this->workos->userManagement()->confirmPasswordReset(
                token: trim($token),
                newPassword: $newPassword,
            );
        } catch (AuthenticationException|BadRequestException|NotFoundException|UnprocessableEntityException $exception) {
            throw ValidationException::withMessages([
                'email' => ['Token invalido ou expirado.'],
            ]);
        } catch (ConfigurationException $exception) {
            throw $this->workosUnavailable(
                'A redefinicao de senha com WorkOS nao esta configurada.',
                $exception,
            );
        } catch (ServerException|TimeoutException|\Throwable $exception) {
            throw $this->workosUnavailable(
                'Nao foi possivel redefinir a senha agora.',
                $exception,
            );
        }

        if (Str::lower(trim($response->user->email)) !== $normalizedEmail) {
            throw ValidationException::withMessages([
                'email' => ['Nao encontramos um usuario com esse e-mail.'],
            ]);
        }

        $user = $this->syncLocalUserFromWorkosUser($response->user, [
            'email_verified_at' => now(),
        ]);

        $user->tokens()->delete();

        return $user;
    }

    public function syncLocalUserFromIdentity(SocialIdentity $identity): User
    {
        return $this->syncLocalUser(
            workosUserId: trim($identity->providerId),
            email: Str::lower(trim($identity->email)),
            name: trim($identity->name),
            avatar: $identity->avatar,
            emailVerified: $identity->emailVerified,
        );
    }

    public function syncProfile(User $user, array $attributes): User
    {
        $this->ensureWorkosConfigured('A atualizacao do perfil com WorkOS nao esta configurada.');

        $user = $this->provisionWorkosUserForLocalUser($user);

        $finalName = array_key_exists('name', $attributes)
            ? trim((string) $attributes['name'])
            : trim((string) $user->name);
        $finalEmail = array_key_exists('email', $attributes)
            ? Str::lower(trim((string) $attributes['email']))
            : Str::lower(trim((string) $user->email));

        try {
            $workosUser = $this->workos->userManagement()->updateUser(
                id: (string) $user->workos_user_id,
                email: $finalEmail !== Str::lower(trim((string) $user->email)) ? $finalEmail : null,
                firstName: $this->firstNameFromDisplayName($finalName),
                lastName: $this->lastNameFromDisplayName($finalName),
                emailVerified: $finalEmail === Str::lower(trim((string) $user->email))
                    ? $user->email_verified_at !== null
                    : false,
            );
        } catch (ConflictException $exception) {
            throw ValidationException::withMessages([
                'email' => ['Este e-mail ja esta em uso.'],
            ]);
        } catch (BadRequestException|UnprocessableEntityException $exception) {
            throw ValidationException::withMessages([
                $this->inputFieldForWorkosException($exception->getMessage(), 'email') => [
                    $this->messageFromWorkosException($exception->getMessage(), 'Nao foi possivel validar os dados informados.'),
                ],
            ]);
        } catch (ConfigurationException $exception) {
            throw $this->workosUnavailable(
                'A atualizacao do perfil com WorkOS nao esta configurada.',
                $exception,
            );
        } catch (NotFoundException|ServerException|TimeoutException|\Throwable $exception) {
            throw $this->workosUnavailable(
                'Nao foi possivel atualizar o perfil no WorkOS agora.',
                $exception,
            );
        }

        return $this->syncLocalUserFromWorkosUser($workosUser, [
            'name' => $finalName,
        ]);
    }

    public function provisionWorkosUserForLocalUser(User $user): User
    {
        $this->ensureWorkosConfigured('O login com WorkOS nao esta configurado.');

        $email = Str::lower(trim((string) $user->email));

        try {
            if (filled($user->workos_user_id)) {
                try {
                    $existingWorkosUser = $this->workos->userManagement()->getUser((string) $user->workos_user_id);

                    return $this->syncLocalUserFromWorkosUser($existingWorkosUser, $this->localProvisioningOverrides($user));
                } catch (NotFoundException) {
                    // Fall through and recreate/link the WorkOS record.
                }
            }

            $existingWorkosUser = $this->findWorkosUserByEmail($email);
            if ($existingWorkosUser) {
                $updatedWorkosUser = $this->workos->userManagement()->updateUser(
                    id: $existingWorkosUser->id,
                    firstName: $this->firstNameFromDisplayName((string) $user->name),
                    lastName: $this->lastNameFromDisplayName((string) $user->name),
                    emailVerified: $user->email_verified_at !== null,
                    passwordHash: (string) $user->getRawOriginal('password'),
                    passwordHashType: CreateUserPasswordHashType::Bcrypt,
                    metadata: $this->metadataFromValues(
                        phone: $this->normalizeNullableString($user->phone),
                        phoneNormalized: $this->normalizeNullableString($user->phone_normalized),
                        whatsappPhone: $this->normalizeNullableString($user->whatsapp_phone),
                        whatsappPhoneNormalized: $this->normalizeNullableString($user->whatsapp_phone_normalized),
                        whatsappOptedInAt: $user->whatsapp_opted_in_at?->toIso8601String(),
                    ),
                );

                return $this->syncLocalUserFromWorkosUser($updatedWorkosUser, $this->localProvisioningOverrides($user));
            }

            $createdWorkosUser = $this->workos->userManagement()->createUser(
                email: $email,
                passwordHash: (string) $user->getRawOriginal('password'),
                passwordHashType: CreateUserPasswordHashType::Bcrypt,
                firstName: $this->firstNameFromDisplayName((string) $user->name),
                lastName: $this->lastNameFromDisplayName((string) $user->name),
                emailVerified: $user->email_verified_at !== null,
                metadata: $this->metadataFromValues(
                    phone: $this->normalizeNullableString($user->phone),
                    phoneNormalized: $this->normalizeNullableString($user->phone_normalized),
                    whatsappPhone: $this->normalizeNullableString($user->whatsapp_phone),
                    whatsappPhoneNormalized: $this->normalizeNullableString($user->whatsapp_phone_normalized),
                    whatsappOptedInAt: $user->whatsapp_opted_in_at?->toIso8601String(),
                ),
            );

            return $this->syncLocalUserFromWorkosUser($createdWorkosUser, $this->localProvisioningOverrides($user));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (ConfigurationException $exception) {
            throw $this->workosUnavailable(
                'O login com WorkOS nao esta configurado.',
                $exception,
            );
        } catch (ServerException|TimeoutException|\Throwable $exception) {
            throw $this->workosUnavailable(
                'Nao foi possivel sincronizar a conta com WorkOS agora.',
                $exception,
            );
        }
    }

    private function syncLocalUserFromWorkosUser(WorkosUser $workosUser, array $overrides = []): User
    {
        return $this->syncLocalUser(
            workosUserId: trim($workosUser->id),
            email: Str::lower(trim($workosUser->email)),
            name: $this->displayNameFromWorkosUser($workosUser),
            avatar: $this->normalizeNullableString($workosUser->profilePictureUrl),
            emailVerified: $workosUser->emailVerified,
            metadata: is_array($workosUser->metadata) ? $workosUser->metadata : [],
            overrides: $overrides,
        );
    }

    private function syncLocalUser(
        string $workosUserId,
        string $email,
        string $name,
        ?string $avatar,
        bool $emailVerified,
        array $metadata = [],
        array $overrides = [],
    ): User {
        $user = User::query()
            ->where('workos_user_id', $workosUserId)
            ->first();

        if (! $user) {
            $user = $this->findLocalUserByEmail($email);
        }

        $name = trim($name) !== '' ? trim($name) : Str::before($email, '@');
        $phoneFromMetadata = $this->normalizeNullableString($metadata['phone'] ?? null);
        $phoneNormalizedFromMetadata = $this->normalizeNullableString($metadata['phone_normalized'] ?? null);
        $whatsappPhoneFromMetadata = $this->normalizeNullableString($metadata['whatsapp_phone'] ?? null);
        $whatsappPhoneNormalizedFromMetadata = $this->normalizeNullableString($metadata['whatsapp_phone_normalized'] ?? null);
        $whatsappOptedInAtFromMetadata = $this->normalizeNullableString($metadata['whatsapp_opted_in_at'] ?? null);

        if (! $user) {
            return User::create([
                'name' => $overrides['name'] ?? $name,
                'email' => $email,
                'workos_user_id' => $workosUserId,
                'email_verified_at' => array_key_exists('email_verified_at', $overrides)
                    ? $overrides['email_verified_at']
                    : ($emailVerified ? now() : null),
                'avatar' => $overrides['avatar'] ?? $avatar,
                'phone' => array_key_exists('phone', $overrides) ? $overrides['phone'] : $phoneFromMetadata,
                'phone_normalized' => array_key_exists('phone_normalized', $overrides)
                    ? $overrides['phone_normalized']
                    : ($phoneNormalizedFromMetadata ?? $this->normalizePhone($phoneFromMetadata)),
                'whatsapp_phone' => array_key_exists('whatsapp_phone', $overrides)
                    ? $overrides['whatsapp_phone']
                    : $whatsappPhoneFromMetadata,
                'whatsapp_phone_normalized' => array_key_exists('whatsapp_phone_normalized', $overrides)
                    ? $overrides['whatsapp_phone_normalized']
                    : ($whatsappPhoneNormalizedFromMetadata ?? $this->normalizePhone($whatsappPhoneFromMetadata)),
                'whatsapp_opted_in_at' => array_key_exists('whatsapp_opted_in_at', $overrides)
                    ? $overrides['whatsapp_opted_in_at']
                    : $whatsappOptedInAtFromMetadata,
                'password' => $overrides['password'] ?? Str::random(40),
            ]);
        }

        $updates = [];

        if ($user->workos_user_id !== $workosUserId) {
            $updates['workos_user_id'] = $workosUserId;
        }

        if (Str::lower(trim((string) $user->email)) !== $email) {
            $updates['email'] = $email;
            if (! array_key_exists('email_verified_at', $overrides)) {
                $updates['email_verified_at'] = $emailVerified ? now() : null;
            }
        } elseif ($emailVerified && $user->email_verified_at === null && ! array_key_exists('email_verified_at', $overrides)) {
            $updates['email_verified_at'] = now();
        }

        if (trim((string) $user->name) === '' && $name !== '') {
            $updates['name'] = $name;
        }

        if (! $user->avatar && $avatar) {
            $updates['avatar'] = $avatar;
        }

        if (! $user->phone && $phoneFromMetadata && ! array_key_exists('phone', $overrides)) {
            $updates['phone'] = $phoneFromMetadata;
            $updates['phone_normalized'] = $phoneNormalizedFromMetadata ?? $this->normalizePhone($phoneFromMetadata);
        }

        if (! $user->whatsapp_phone && $whatsappPhoneFromMetadata && ! array_key_exists('whatsapp_phone', $overrides)) {
            $updates['whatsapp_phone'] = $whatsappPhoneFromMetadata;
            $updates['whatsapp_phone_normalized'] = $whatsappPhoneNormalizedFromMetadata ?? $this->normalizePhone($whatsappPhoneFromMetadata);
        }

        if ($user->whatsapp_opted_in_at === null && $whatsappOptedInAtFromMetadata && ! array_key_exists('whatsapp_opted_in_at', $overrides)) {
            $updates['whatsapp_opted_in_at'] = $whatsappOptedInAtFromMetadata;
        }

        foreach ($overrides as $key => $value) {
            if ($key === 'password') {
                continue;
            }

            $updates[$key] = $value;
        }

        if ($updates !== []) {
            $user->fill($updates);
            $user->save();
        }

        return $user->refresh();
    }

    private function resolveLoginEmail(string $login): string
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $normalizedEmail = Str::lower($login);
            $localUser = $this->findLocalUserByEmail($normalizedEmail);

            if ($localUser && ! filled($localUser->workos_user_id)) {
                $this->provisionWorkosUserForLocalUser($localUser);
            }

            return $normalizedEmail;
        }

        $localUser = User::query()
            ->where(function ($query) use ($login): void {
                $query->where('phone', $login);

                $variants = $this->phoneVariants($login);
                if ($variants !== []) {
                    $query->orWhereIn('phone_normalized', $variants);
                }
            })
            ->first();

        if (! $localUser) {
            throw ValidationException::withMessages([
                'login' => ['O login ou a senha estao incorretos.'],
            ]);
        }

        if (! filled($localUser->workos_user_id)) {
            $localUser = $this->provisionWorkosUserForLocalUser($localUser);
        }

        return Str::lower(trim((string) $localUser->email));
    }

    private function findLocalUserByEmail(string $email): ?User
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    private function findWorkosUserByEmail(string $email): ?WorkosUser
    {
        $response = $this->workos->userManagement()->listUsers(
            limit: 1,
            email: $email,
        );

        return $response->data[0] ?? null;
    }

    private function displayNameFromWorkosUser(WorkosUser $workosUser): string
    {
        return trim(implode(' ', array_filter([
            trim((string) $workosUser->firstName),
            trim((string) $workosUser->lastName),
        ])));
    }

    private function firstNameFromDisplayName(string $name): ?string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $firstName = trim((string) ($parts[0] ?? ''));

        return $firstName !== '' ? $firstName : null;
    }

    private function lastNameFromDisplayName(string $name): ?string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        array_shift($parts);

        $lastName = trim(implode(' ', $parts));

        return $lastName !== '' ? $lastName : null;
    }

    private function metadataFromValues(
        ?string $phone = null,
        ?string $phoneNormalized = null,
        ?string $whatsappPhone = null,
        ?string $whatsappPhoneNormalized = null,
        ?string $whatsappOptedInAt = null,
    ): ?array {
        $metadata = array_filter([
            'phone' => $this->normalizeNullableString($phone),
            'phone_normalized' => $this->normalizeNullableString($phoneNormalized),
            'whatsapp_phone' => $this->normalizeNullableString($whatsappPhone),
            'whatsapp_phone_normalized' => $this->normalizeNullableString($whatsappPhoneNormalized),
            'whatsapp_opted_in_at' => $this->normalizeNullableString($whatsappOptedInAt),
        ], static fn ($value): bool => $value !== null);

        return $metadata !== [] ? $metadata : null;
    }

    private function localProvisioningOverrides(User $user): array
    {
        return [
            'name' => $user->name,
            'phone' => $user->phone,
            'phone_normalized' => $user->phone_normalized,
            'whatsapp_phone' => $user->whatsapp_phone,
            'whatsapp_phone_normalized' => $user->whatsapp_phone_normalized,
            'whatsapp_opted_in_at' => $user->whatsapp_opted_in_at,
        ];
    }

    private function ensureWorkosConfigured(string $message): void
    {
        if (
            trim((string) config('services.workos.client_id', '')) === ''
            || trim((string) config('services.workos.api_key', '')) === ''
        ) {
            throw new \RuntimeException($message);
        }
    }

    private function workosUnavailable(string $message, \Throwable $exception): \RuntimeException
    {
        return new \RuntimeException($message, 0, $exception);
    }

    private function inputFieldForWorkosException(string $message, string $fallback): string
    {
        $normalizedMessage = Str::lower($message);

        return match (true) {
            str_contains($normalizedMessage, 'password') => 'password',
            str_contains($normalizedMessage, 'email') => 'email',
            default => $fallback,
        };
    }

    private function messageFromWorkosException(string $message, string $fallback): string
    {
        $normalizedMessage = trim($message);

        return $normalizedMessage !== '' ? $normalizedMessage : $fallback;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return $digits !== '' ? $digits : null;
    }

    private function phoneVariants(string $login): array
    {
        $digits = preg_replace('/\D+/', '', $login);
        if ($digits === '' || $digits === false) {
            return [];
        }

        $variants = [$digits];

        if (! str_starts_with($digits, '55')) {
            $variants[] = '55'.$digits;
        } elseif (strlen($digits) > 2) {
            $variants[] = substr($digits, 2);
        }

        return array_values(array_unique(array_filter($variants)));
    }
}
