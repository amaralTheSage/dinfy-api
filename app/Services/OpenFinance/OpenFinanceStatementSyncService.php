<?php

namespace App\Services\OpenFinance;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Support\BrazilDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpenFinanceStatementSyncService
{
    private const PROVIDER = 'openfinance';

    /**
     * @return array<string, int>
     */
    public function handle(?int $limit = null): array
    {
        $stats = [
            'authorization_checked' => 0,
            'protocols_requested' => 0,
            'protocols_checked' => 0,
            'transactions_imported' => 0,
            'accounts_skipped' => 0,
        ];

        if (! $this->isConfigured()) {
            Log::warning('OpenFinance statement sync skipped because configuration is incomplete.');

            return $stats;
        }

        $limit = $limit !== null && $limit > 0
            ? $limit
            : max(1, (int) config('openfinance.statement_sync.batch_size', 10));

        $pendingStats = $this->completePendingProtocols($limit);
        $stats = $this->mergeStats($stats, $pendingStats);

        $remaining = max(1, $limit - $pendingStats['protocols_checked']);

        $authorizationStats = $this->refreshPendingAuthorizations($remaining);
        $stats = $this->mergeStats($stats, $authorizationStats);

        $remaining = max(1, $limit - $pendingStats['protocols_checked'] - $authorizationStats['authorization_checked']);

        return $this->mergeStats($stats, $this->requestDueProtocols($remaining));
    }

    /**
     * @return array<string, int>
     */
    private function completePendingProtocols(int $limit): array
    {
        $stats = $this->emptyStats();
        $threshold = now()->subMinutes($this->pendingCheckIntervalMinutes());

        $accounts = FinancialAccount::query()
            ->with('user')
            ->whereNotNull('openfinance_account_hash')
            ->whereNotNull('openfinance_last_statement_unique_id')
            ->whereIn('openfinance_statement_status', ['PROCESSING', 'REQUESTED', 'PENDING'])
            ->where(function ($query) use ($threshold): void {
                $query->whereNull('openfinance_last_statement_checked_at')
                    ->orWhere('openfinance_last_statement_checked_at', '<=', $threshold);
            })
            ->oldest('openfinance_last_statement_checked_at')
            ->limit($limit)
            ->get();

        foreach ($accounts as $account) {
            $stats['protocols_checked']++;

            try {
                $stats['transactions_imported'] += $this->checkPendingProtocol($account);
            } catch (\Throwable $exception) {
                $stats['accounts_skipped']++;

                Log::warning('OpenFinance statement result sync failed.', [
                    'financial_account_id' => $account->id,
                    'user_id' => $account->user_id,
                    'unique_id' => $this->maskIdentifier((string) $account->openfinance_last_statement_unique_id),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function checkPendingProtocol(FinancialAccount $account): int
    {
        $user = $account->user;
        if (! $user instanceof User) {
            $account->load('user');
            $user = $account->user;
        }

        if (! $user instanceof User || ! $this->hasValidDocument($user)) {
            $this->markStatementError($account, 'CPF/CNPJ ausente ou invalido para consulta Open Finance.');

            return 0;
        }

        try {
            $response = $this->providerRequest(
                'get',
                'statement/openfinance/'.rawurlencode((string) $account->openfinance_last_statement_unique_id),
                $this->payerHeaders((string) $user->cpf_cnpj),
            );
        } catch (ConnectionException $exception) {
            $this->markStatementError($account, 'Falha de conexao ao consultar protocolo.');
            throw $exception;
        }

        $account->forceFill(['openfinance_last_statement_checked_at' => now()])->save();

        if (! $response->successful()) {
            $this->markResultFailure($account, $response);

            return 0;
        }

        $body = $response->json() ?? [];
        if (! is_array($body) || (! array_key_exists('statement', $body) && strtoupper((string) data_get($body, 'status')) === 'PROCESSING')) {
            $this->markStatementPending($account, data_get($body, 'status', 'PROCESSING'));

            return 0;
        }

        $imported = $this->importStatement($account, $body);
        $nextStatementAt = $this->nextAllowedStatementAt($account->openfinance_last_statement_requested_at);

        $account->forceFill([
            'openfinance_statement_status' => 'COMPLETED',
            'openfinance_statement_error' => null,
            'openfinance_last_statement_result_at' => now(),
            'openfinance_next_statement_at' => $nextStatementAt,
            'openfinance_synced_at' => now(),
        ])->save();

        Log::info('OpenFinance statement imported.', [
            'financial_account_id' => $account->id,
            'user_id' => $account->user_id,
            'unique_id' => $this->maskIdentifier((string) $account->openfinance_last_statement_unique_id),
            'transactions_imported' => $imported,
        ]);

        return $imported;
    }

    /**
     * @return array<string, int>
     */
    private function refreshPendingAuthorizations(int $limit): array
    {
        $stats = $this->emptyStats();

        $accounts = FinancialAccount::query()
            ->with('user')
            ->whereNotNull('openfinance_account_hash')
            ->whereNull('openfinance_id')
            ->where(function ($query): void {
                $query->where('openfinance_status', 'authorization_pending')
                    ->orWhereNull('openfinance_status');
            })
            ->where(function ($query): void {
                $query->whereNull('openfinance_next_statement_at')
                    ->orWhere('openfinance_next_statement_at', '<=', now());
            })
            ->oldest('openfinance_synced_at')
            ->limit($limit)
            ->get();

        foreach ($accounts as $account) {
            $stats['authorization_checked']++;

            try {
                $this->refreshAuthorization($account);
            } catch (\Throwable $exception) {
                $stats['accounts_skipped']++;

                Log::warning('OpenFinance authorization refresh failed.', [
                    'financial_account_id' => $account->id,
                    'user_id' => $account->user_id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function refreshAuthorization(FinancialAccount $account): void
    {
        $user = $account->user;

        if (! $user instanceof User || ! $this->hasValidDocument($user)) {
            $this->deferAccount($account, 'CPF/CNPJ ausente ou invalido para consulta da autorizacao.');

            return;
        }

        $response = $this->providerRequest(
            'get',
            'account/'.rawurlencode((string) $account->openfinance_account_hash),
            $this->payerHeaders((string) $user->cpf_cnpj),
        );

        if (! $response->successful()) {
            $this->deferAccount($account, $this->messageFromProvider($response, 'Autorizacao ainda nao confirmada.'));

            return;
        }

        $remoteAccount = data_get($response->json() ?? [], 'accounts', []);
        if (! is_array($remoteAccount)) {
            $remoteAccount = [];
        }

        $openfinanceId = data_get($remoteAccount, 'openfinanceId');
        $openfinanceLink = data_get($remoteAccount, 'openfinanceLink');

        $account->forceFill([
            'openfinance_id' => $openfinanceId ?: $account->openfinance_id,
            'openfinance_link' => $openfinanceLink ?: $account->openfinance_link,
            'openfinance_status' => $openfinanceId ? 'active' : 'authorization_pending',
            'openfinance_statement_error' => $openfinanceId ? null : 'Aguardando autorizacao do banco.',
            'openfinance_synced_at' => now(),
            'openfinance_next_statement_at' => $openfinanceId ? now() : now()->addMinutes($this->rateLimitMinutes()),
        ])->save();
    }

    /**
     * @return array<string, int>
     */
    private function requestDueProtocols(int $limit): array
    {
        $stats = $this->emptyStats();
        $rateLimitThreshold = now()->subMinutes($this->rateLimitMinutes());

        $accounts = FinancialAccount::query()
            ->with('user')
            ->whereNotNull('openfinance_account_hash')
            ->whereNotNull('openfinance_id')
            ->where('openfinance_status', 'active')
            ->where(function ($query): void {
                $query->whereNull('openfinance_statement_status')
                    ->orWhereNotIn('openfinance_statement_status', ['PROCESSING', 'REQUESTED', 'PENDING']);
            })
            ->where(function ($query): void {
                $query->whereNull('openfinance_next_statement_at')
                    ->orWhere('openfinance_next_statement_at', '<=', now());
            })
            ->where(function ($query) use ($rateLimitThreshold): void {
                $query->whereNull('openfinance_last_statement_requested_at')
                    ->orWhere('openfinance_last_statement_requested_at', '<=', $rateLimitThreshold);
            })
            ->oldest('openfinance_next_statement_at')
            ->limit($limit)
            ->get();

        foreach ($accounts as $account) {
            try {
                if ($this->requestProtocol($account)) {
                    $stats['protocols_requested']++;
                } else {
                    $stats['accounts_skipped']++;
                }
            } catch (\Throwable $exception) {
                $stats['accounts_skipped']++;

                Log::warning('OpenFinance statement protocol sync failed.', [
                    'financial_account_id' => $account->id,
                    'user_id' => $account->user_id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function requestProtocol(FinancialAccount $account): bool
    {
        $user = $account->user;

        if (! $user instanceof User || ! $this->hasValidDocument($user)) {
            $this->markStatementError($account, 'CPF/CNPJ ausente ou invalido para solicitar extrato.');

            return false;
        }

        if (! $this->canRequestProtocol($account)) {
            return false;
        }

        $payload = $this->statementPayload($account);

        try {
            $response = $this->providerRequest(
                'post',
                'statement/openfinance',
                $this->payerHeaders((string) $user->cpf_cnpj),
                $payload,
            );
        } catch (ConnectionException $exception) {
            $this->markStatementError($account, 'Falha de conexao ao solicitar protocolo.');
            throw $exception;
        }

        if (! $response->successful()) {
            $this->markProtocolFailure($account, $response);

            return false;
        }

        $body = $response->json() ?? [];
        $uniqueId = trim((string) data_get($body, 'uniqueId'));

        if ($uniqueId === '') {
            $this->markStatementError($account, 'A TecnoSpeed nao retornou uniqueId para o protocolo.');

            return false;
        }

        $account->forceFill([
            'openfinance_last_statement_unique_id' => $uniqueId,
            'openfinance_statement_status' => strtoupper((string) (data_get($body, 'status') ?: 'PROCESSING')),
            'openfinance_statement_error' => null,
            'openfinance_last_statement_requested_at' => now(),
            'openfinance_last_statement_checked_at' => null,
            'openfinance_next_statement_at' => now()->addMinutes($this->rateLimitMinutes()),
        ])->save();

        Log::info('OpenFinance statement protocol requested by sync.', [
            'financial_account_id' => $account->id,
            'user_id' => $account->user_id,
            'unique_id' => $this->maskIdentifier($uniqueId),
            'date_start' => $payload['dateStart'] ?? null,
            'date_end' => $payload['dateEnd'] ?? null,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function importStatement(FinancialAccount $account, array $body): int
    {
        $creditTransactions = data_get($body, 'transaction.credit', []);
        $debitTransactions = data_get($body, 'transaction.debit', []);
        $transactions = array_merge(
            is_array($creditTransactions) ? $creditTransactions : [],
            is_array($debitTransactions) ? $debitTransactions : [],
        );

        $imported = 0;

        foreach ($transactions as $transaction) {
            if (! is_array($transaction)) {
                continue;
            }

            if ($this->upsertTransaction($account, $body, $transaction)) {
                $imported++;
            }
        }

        $finalBalance = data_get($body, 'balance.final.balance');
        $data = $account->data ?? [];
        $data['openfinance'] = array_merge($data['openfinance'] ?? [], [
            'lastStatement' => [
                'uniqueId' => data_get($body, 'statement.uniqueId'),
                'bankCode' => data_get($body, 'statement.bankCode'),
                'totalTransactions' => data_get($body, 'statement.totalTransactions'),
                'resultAt' => now()->toIso8601String(),
                'importedTransactions' => $imported,
                'duplicatedTransactionsIgnored' => $this->countDuplicatedTransactions($body),
            ],
            'lastBalance' => data_get($body, 'balance.final'),
        ]);

        $updates = ['data' => $data];
        if (is_numeric($finalBalance)) {
            $updates['balance'] = $finalBalance;
        }

        $account->forceFill($updates)->save();

        return $imported;
    }

    /**
     * @param  array<string, mixed>  $statementBody
     * @param  array<string, mixed>  $payload
     */
    private function upsertTransaction(FinancialAccount $account, array $statementBody, array $payload): bool
    {
        $providerTransactionId = $this->providerTransactionId($account, $statementBody, $payload);

        $existing = FinancialTransaction::withTrashed()
            ->where('account_id', $account->id)
            ->where('provider', self::PROVIDER)
            ->where('provider_transaction_id', $providerTransactionId)
            ->first();

        if ($existing) {
            return false;
        }

        $type = strtoupper((string) ($payload['transactionType'] ?? ''));
        $type = $type === 'CREDIT' ? 'CREDIT' : 'DEBIT';
        $amount = (float) str_replace(',', '.', (string) ($payload['amount'] ?? 0));
        $date = Carbon::parse((string) ($payload['date'] ?? now()->toDateString()))->startOfDay();

        FinancialTransaction::query()->create([
            'provider' => self::PROVIDER,
            'provider_transaction_id' => $providerTransactionId,
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'type' => $type,
            'amount' => abs($amount),
            'currency' => 'BRL',
            'occurred_at' => $date,
            'description' => $this->truncate($payload['description'] ?? null, 255),
            'merchant' => $this->truncate($payload['name'] ?? null, 255),
            'category' => $this->truncate($payload['category'] ?? null, 100),
            'data' => [
                'provider' => self::PROVIDER,
                'statementUniqueId' => data_get($statementBody, 'statement.uniqueId'),
                'accountHash' => data_get($statementBody, 'statement.accountHash'),
                'bankCode' => data_get($statementBody, 'statement.bankCode'),
                'raw' => $payload,
            ],
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $statementBody
     * @param  array<string, mixed>  $payload
     */
    private function providerTransactionId(FinancialAccount $account, array $statementBody, array $payload): string
    {
        $externalId = trim((string) ($payload['transactionId'] ?? $payload['fitid'] ?? ''));
        if ($externalId !== '') {
            return $externalId;
        }

        return sha1(json_encode([
            'accountHash' => data_get($statementBody, 'statement.accountHash', $account->openfinance_account_hash),
            'type' => $payload['transactionType'] ?? null,
            'amount' => $payload['amount'] ?? null,
            'date' => $payload['date'] ?? null,
            'sequence' => $payload['sequence'] ?? null,
            'description' => $payload['description'] ?? null,
            'name' => $payload['name'] ?? null,
        ], JSON_THROW_ON_ERROR));
    }

    private function canRequestProtocol(FinancialAccount $account): bool
    {
        $lastRequestedAt = $account->openfinance_last_statement_requested_at;

        return $lastRequestedAt === null
            || $lastRequestedAt->lte(now()->subMinutes($this->rateLimitMinutes()));
    }

    /**
     * @return array<string, mixed>
     */
    private function statementPayload(FinancialAccount $account): array
    {
        $dateEnd = now()->toDateString();
        $lookbackStart = now()->subDays($this->lookbackDays())->toDateString();
        $lastResultStart = $account->openfinance_last_statement_result_at
            ? $account->openfinance_last_statement_result_at->copy()->subDay()->toDateString()
            : null;

        $dateStart = $lastResultStart !== null && $lastResultStart > $lookbackStart
            ? $lastResultStart
            : $lookbackStart;

        return [
            'today' => false,
            'accountHash' => $account->openfinance_account_hash,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ];
    }

    private function markResultFailure(FinancialAccount $account, ClientResponse $response): void
    {
        $message = $this->messageFromProvider($response, 'Resultado do extrato ainda indisponivel.');
        $status = $response->status() === 401 ? 'AUTHORIZATION_PENDING' : 'RESULT_PENDING';

        $account->forceFill([
            'openfinance_statement_status' => $status,
            'openfinance_statement_error' => $message,
            'openfinance_next_statement_at' => now()->addMinutes($this->rateLimitMinutes()),
        ])->save();
    }

    private function markProtocolFailure(FinancialAccount $account, ClientResponse $response): void
    {
        $message = $this->messageFromProvider($response, 'Nao foi possivel solicitar o protocolo do extrato.');
        $status = $response->status() === 401 ? 'AUTHORIZATION_PENDING' : 'PROTOCOL_ERROR';

        $account->forceFill([
            'openfinance_statement_status' => $status,
            'openfinance_statement_error' => $message,
            'openfinance_next_statement_at' => now()->addMinutes($this->rateLimitMinutes()),
        ])->save();
    }

    private function markStatementPending(FinancialAccount $account, mixed $status): void
    {
        $account->forceFill([
            'openfinance_statement_status' => strtoupper((string) ($status ?: 'PROCESSING')),
            'openfinance_next_statement_at' => $this->nextAllowedStatementAt($account->openfinance_last_statement_requested_at),
        ])->save();
    }

    private function markStatementError(FinancialAccount $account, string $message): void
    {
        $account->forceFill([
            'openfinance_statement_status' => 'ERROR',
            'openfinance_statement_error' => $message,
            'openfinance_next_statement_at' => now()->addMinutes($this->rateLimitMinutes()),
        ])->save();
    }

    private function deferAccount(FinancialAccount $account, string $message): void
    {
        $account->forceFill([
            'openfinance_status' => 'authorization_pending',
            'openfinance_statement_error' => $message,
            'openfinance_synced_at' => now(),
            'openfinance_next_statement_at' => now()->addMinutes($this->rateLimitMinutes()),
        ])->save();
    }

    private function nextAllowedStatementAt(?Carbon $lastRequestedAt): Carbon
    {
        if ($lastRequestedAt === null) {
            return now()->addMinutes($this->rateLimitMinutes());
        }

        $next = $lastRequestedAt->copy()->addMinutes($this->rateLimitMinutes());

        return $next->isPast() ? now()->addMinutes($this->rateLimitMinutes()) : $next;
    }

    private function messageFromProvider(ClientResponse $response, string $fallback): string
    {
        $body = $response->json() ?? [];

        return (string) (
            data_get($body, 'message')
            ?: data_get($body, 'error')
            ?: data_get($body, 'errors.0.message')
            ?: $fallback
        );
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function countDuplicatedTransactions(array $body): int
    {
        $credit = data_get($body, 'transactionDuplicated.credit', []);
        $debit = data_get($body, 'transactionDuplicated.debit', []);

        return (is_array($credit) ? count($credit) : 0)
            + (is_array($debit) ? count($debit) : 0);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, string>  $headers
     */
    private function providerRequest(string $method, string $path, array $headers, ?array $payload = null): ClientResponse
    {
        $request = Http::withHeaders($headers)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(30);

        $url = $this->baseUrl().ltrim($path, '/');

        return match (strtolower($method)) {
            'get' => $request->get($url),
            default => $request->post($url, $payload ?? []),
        };
    }

    /**
     * @return array<string, string>
     */
    private function payerHeaders(string $document): array
    {
        return [
            'tokensh' => (string) config('openfinance.tokensh'),
            'cnpjsh' => (string) config('openfinance.cnpjsh'),
            'payercpfcnpj' => BrazilDocument::normalize($document) ?? $document,
        ];
    }

    private function hasValidDocument(User $user): bool
    {
        return BrazilDocument::isValid((string) $user->cpf_cnpj);
    }

    private function isConfigured(): bool
    {
        return trim((string) config('openfinance.url')) !== ''
            && trim((string) config('openfinance.tokensh')) !== ''
            && trim((string) config('openfinance.cnpjsh')) !== '';
    }

    private function baseUrl(): string
    {
        $url = trim((string) config('openfinance.url'));

        return str_ends_with($url, '/') ? $url : $url.'/';
    }

    private function rateLimitMinutes(): int
    {
        return max(60, (int) config('openfinance.statement_sync.rate_limit_minutes', 60));
    }

    private function pendingCheckIntervalMinutes(): int
    {
        return max(5, (int) config('openfinance.statement_sync.pending_check_interval_minutes', 15));
    }

    private function lookbackDays(): int
    {
        return max(1, (int) config('openfinance.statement_sync.lookback_days', 7));
    }

    /**
     * @return array<string, int>
     */
    private function emptyStats(): array
    {
        return [
            'authorization_checked' => 0,
            'protocols_requested' => 0,
            'protocols_checked' => 0,
            'transactions_imported' => 0,
            'accounts_skipped' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $base
     * @param  array<string, int>  $next
     * @return array<string, int>
     */
    private function mergeStats(array $base, array $next): array
    {
        foreach ($next as $key => $value) {
            $base[$key] = ($base[$key] ?? 0) + $value;
        }

        return $base;
    }

    private function truncate(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? Str::limit($value, $limit, '') : null;
    }

    private function maskIdentifier(string $value): string
    {
        $value = trim($value);

        if (strlen($value) <= 8) {
            return substr($value, 0, 2).'***';
        }

        return substr($value, 0, 4).'...'.substr($value, -4);
    }
}
