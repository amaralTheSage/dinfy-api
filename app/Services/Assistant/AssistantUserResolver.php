<?php

namespace App\Services\Assistant;

use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Validation\ValidationException;

class AssistantUserResolver
{
    public function resolve(string $phone): User
    {
        $variants = PhoneNormalizer::variants($phone);

        if ($variants === []) {
            throw ValidationException::withMessages([
                'phone' => ['Informe um telefone válido para localizar o usuário.'],
            ]);
        }

        $users = User::query()
            ->where(function ($query) use ($phone, $variants): void {
                $query->where('whatsapp_phone', $phone)
                    ->orWhereIn('whatsapp_phone_normalized', $variants)
                    ->orWhere('phone', $phone)
                    ->orWhereIn('phone_normalized', $variants);
            })
            ->limit(3)
            ->get();

        if ($users->count() === 1) {
            return $users->firstOrFail();
        }

        if ($users->isEmpty()) {
            throw ValidationException::withMessages([
                'phone' => ['Nenhum usuário foi encontrado para esse telefone.'],
            ]);
        }

        throw ValidationException::withMessages([
            'phone' => ['Mais de um usuário foi encontrado para esse telefone.'],
        ]);
    }
}
