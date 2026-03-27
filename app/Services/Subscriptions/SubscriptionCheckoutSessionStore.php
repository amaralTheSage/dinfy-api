<?php

namespace App\Services\Subscriptions;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionCheckoutSessionStore
{
    private const CACHE_PREFIX = 'subscription_checkout_session:';

    /**
     * @return array{id:string,user_id:int,plan_code:string,expires_at:Carbon}
     */
    public function create(User $user, string $planCode): array
    {
        Log::info('3. Entrou em SubscriptionCheckoutSessionStore@create', [
            'user_id' => $user->id,
            'plan' => $planCode,
        ]);

        $ttlMinutes = max((int) config('subscriptions.payment_session_ttl_minutes', 30), 1);
        $expiresAt = now()->addMinutes($ttlMinutes);
        $sessionId = (string) Str::uuid();

        Cache::put($this->cacheKey($sessionId), [
            'user_id' => $user->id,
            'plan_code' => $planCode,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return [
            'id' => $sessionId,
            'user_id' => $user->id,
            'plan_code' => $planCode,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @return array{user_id:int,plan_code:string,expires_at:?Carbon}|null
     */
    public function find(string $sessionId): ?array
    {
        Log::info('3. Entrou em SubscriptionCheckoutSessionStore@find', [
            'session_id' => $sessionId,
        ]);

        $payload = Cache::get($this->cacheKey($sessionId));
        if (!is_array($payload)) {
            Log::warning('3. Sessao nao encontrada em SubscriptionCheckoutSessionStore@find', [
                'session_id' => $sessionId,
            ]);

            return null;
        }

        return [
            'user_id' => (int) ($payload['user_id'] ?? 0),
            'plan_code' => (string) ($payload['plan_code'] ?? ''),
            'expires_at' => isset($payload['expires_at']) ? Carbon::parse((string) $payload['expires_at']) : null,
        ];
    }

    public function forget(string $sessionId): void
    {
        Log::info('8. Removendo sessao em SubscriptionCheckoutSessionStore@forget', [
            'session_id' => $sessionId,
        ]);

        Cache::forget($this->cacheKey($sessionId));
    }

    private function cacheKey(string $sessionId): string
    {
        return self::CACHE_PREFIX . $sessionId;
    }
}
