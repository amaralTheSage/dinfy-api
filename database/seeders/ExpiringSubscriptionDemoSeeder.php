<?php

namespace Database\Seeders;

use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\Subscriptions\SubscriptionManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExpiringSubscriptionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $plan = config('subscriptions.plans.monthly', []);
        $now = now();
        $renewsAt = $now->copy()->addMinute();
        $startedAt = $renewsAt->copy()->subMonth();

        $amount = (float) ($plan['amount'] ?? 19.90);
        if ($amount <= 0) {
            $amount = 19.90;
        }

        $currencyId = (string) ($plan['currency_id'] ?? config('subscriptions.currency', 'BRL') ?? 'BRL');
        if ($currencyId === '') {
            $currencyId = 'BRL';
        }

        $frequency = (int) ($plan['frequency'] ?? 1);
        if ($frequency <= 0) {
            $frequency = 1;
        }

        $frequencyType = (string) ($plan['frequency_type'] ?? 'months');
        if ($frequencyType === '') {
            $frequencyType = 'months';
        }

        /** @var User $user */
        $user = User::query()->updateOrCreate(
            ['email' => 'p@p.com'],
            [
                'name' => 'Expiring Subscription Demo',
                'password' => 'p',
                'phone' => null,
                'phone_normalized' => null,
            ],
        );

        $user->subscriptions()->delete();

        $externalReference = sprintf(
            'dinfy-u-%d-p-monthly-seed-%s',
            $user->id,
            (string) Str::uuid(),
        );

        $paymentId = 'seed_pay_'.Str::lower(Str::random(12));

        $subscription = UserSubscription::query()->create([
            'user_id' => $user->id,
            'provider' => 'pix',
            'plan_code' => 'monthly',
            'status' => 'active',
            'external_reference' => $externalReference,
            'mercado_pago_payment_id' => $paymentId,
            'transaction_amount' => $amount,
            'currency_id' => $currencyId,
            'frequency' => $frequency,
            'frequency_type' => $frequencyType,
            'payer_document_type' => 'CPF',
            'payer_document_number' => '12345678909',
            'latest_payment_status' => 'approved',
            'latest_payment_status_detail' => 'accredited',
            'started_at' => $startedAt,
            'next_payment_at' => $renewsAt,
            'last_notified_at' => $now,
        ]);

        SubscriptionInvoice::query()->create([
            'user_subscription_id' => $subscription->id,
            'provider' => 'mercado_pago',
            'provider_payment_id' => $paymentId,
            'external_reference' => $externalReference,
            'transaction_amount' => $amount,
            'currency_id' => $currencyId,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'paid_at' => $startedAt,
        ]);

        app(SubscriptionManager::class)->syncUserSummary($user->fresh());
    }
}
