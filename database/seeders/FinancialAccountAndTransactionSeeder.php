<?php

namespace Database\Seeders;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class FinancialAccountAndTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var User $user */
        $user = User::query()->firstOrCreate(
            ['email' => 't@t'],
            ['name' => 'Gabriel Amaral', 'password' => 't']
        );

        $account = FinancialAccount::query()->updateOrCreate(
            ['id' => '4f61bd6d-e6fc-44b2-9c4b-5609058de7ab'],
            [
                'user_id' => $user->id,
                'type' => 'CREDIT',
                'subtype' => 'CREDIT_CARD',
                'name' => 'Nubank Mastercard Ultravioleta',
                'marketing_name' => 'Nubank Ultravioleta',
                'tax_number' => '***.***.123-22',
                'owner' => 'Marcos Pangriel',
                'number_last4' => '1234',
                'balance' => 142.41,
                'currency' => 'BRL',
                'credit_data' => [
                    'limit' => 5000.00,
                    'available' => 4857.59,
                    'statementCloseDay' => 20,
                    'statementDueDay' => 28,
                ],
                'data' => [
                    'provider' => 'open-finance-mock',
                    'consentId' => (string) Str::uuid(),
                ],
            ]
        );

        $now = Carbon::now();
        $transactions = [
            [
                'type' => 'DEBIT',
                'amount' => 39.90,
                'occurred_at' => $now->copy()->subDays(2),
                'description' => 'Spotify',
                'merchant' => 'Spotify',
                'category' => 'SUBSCRIPTIONS',
            ],
            [
                'type' => 'DEBIT',
                'amount' => 18.50,
                'occurred_at' => $now->copy()->subDays(1),
                'description' => 'Padaria',
                'merchant' => 'Padaria do Bairro',
                'category' => 'FOOD',
            ],
            [
                'type' => 'CREDIT',
                'amount' => 2500.00,
                'occurred_at' => $now->copy()->subDays(5),
                'description' => 'Salário',
                'merchant' => 'Empresa',
                'category' => 'INCOME',
            ],
        ];

        foreach ($transactions as $t) {
            FinancialTransaction::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'account_id' => $account->id,
                    'occurred_at' => $t['occurred_at'],
                    'amount' => $t['amount'],
                    'description' => $t['description'],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => $t['type'],
                    'currency' => 'BRL',
                    'merchant' => $t['merchant'],
                    'category' => $t['category'],
                    'data' => [
                        'source' => 'seed',
                    ],
                ]
            );
        }
    }
}
