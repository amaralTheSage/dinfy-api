<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use App\Models\User;
use App\Models\UserAddress;
use App\Support\BrazilDocument;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OpenFinanceController extends Controller
{
    private const PAYER_ALREADY_EXISTS_INTERNAL_CODE = 7632;

    private const ADDRESS_TYPE = 'openfinance';

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $accounts = FinancialAccount::query()
            ->where('user_id', $user->id)
            ->whereNotNull('openfinance_account_hash')
            ->orderByDesc('openfinance_synced_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FinancialAccount $account): array => $this->accountSummary($account))
            ->values();

        return response()->json([
            'cpfCnpj' => $user->cpf_cnpj,
            'address' => $this->addressForResponse($user->openFinanceAddress()->first()),
            'accounts' => $accounts,
        ]);
    }

    public function connect(Request $request): JsonResponse
    {
        if ($response = $this->configurationProblem()) {
            return $response;
        }

        $validated = $this->validateConnectRequest($request);
        $user = $request->user();
        $document = $this->resolveUserDocument($user, $validated['cpfCnpj'] ?? null);
        $address = $this->storeOpenFinanceAddress($user, $validated);

        $payerPayload = $this->payerPayload($user, $document, $validated);
        $accountPayload = $this->accountPayload($validated);

        Log::info('OpenFinance connect started.', [
            'user_id' => $user->id,
            'cpf_cnpj' => BrazilDocument::mask($document),
            'bank_code' => $validated['bankCode'],
            'account_last4' => $this->last4($validated['accountNumber']),
        ]);

        try {
            $payerResponse = $this->providerRequest('post', 'payer', $this->softwareHouseHeaders(), $payerPayload);
            $this->logProviderResponse('create_payer', $payerResponse);

            if (! $payerResponse->successful()) {
                $payerData = $payerResponse->json() ?? [];

                if ($this->hasInternalCode($payerData, self::PAYER_ALREADY_EXISTS_INTERNAL_CODE)) {
                    $payerResponse = $this->providerRequest(
                        'put',
                        'payer',
                        $this->payerHeaders($document),
                        ['statementActived' => true],
                    );
                    $this->logProviderResponse('activate_existing_payer', $payerResponse);
                }
            }

            if (! $payerResponse->successful()) {
                return $this->providerFailure($payerResponse, 'Nao foi possivel criar o pagador Open Finance.');
            }

            $accountResponse = $this->providerRequest(
                'post',
                'account',
                $this->payerHeaders($document),
                [$accountPayload],
            );
            $this->logProviderResponse('create_account', $accountResponse);

            if (! $accountResponse->successful()) {
                return $this->providerFailure($accountResponse, 'Nao foi possivel criar a conta Open Finance.');
            }
        } catch (ConnectionException $exception) {
            Log::warning('OpenFinance provider connection failed.', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Nao foi possivel conectar com a TecnoSpeed agora.',
            ], 504);
        }

        $accountData = $this->firstAccountFromResponse($accountResponse->json() ?? []);
        $accountHash = trim((string) data_get($accountData, 'accountHash'));

        if ($accountHash === '') {
            Log::error('OpenFinance account response did not include accountHash.', [
                'user_id' => $user->id,
                'body' => $this->compactLogBody($accountResponse->json() ?? $accountResponse->body()),
            ]);

            return response()->json([
                'message' => 'A TecnoSpeed criou a conta, mas nao retornou o identificador esperado.',
            ], 502);
        }

        $account = $this->storeOpenFinanceAccount(
            user: $user,
            validated: $validated,
            accountData: $accountData,
            accountHash: $accountHash,
        );

        Log::info('OpenFinance connect finished.', [
            'user_id' => $user->id,
            'financial_account_id' => $account->id,
            'account_hash' => $this->maskIdentifier($accountHash),
            'has_openfinance_link' => filled($account->openfinance_link),
        ]);

        return response()->json([
            'message' => 'Conta Open Finance criada. Abra o link para autorizar no banco.',
            'warning' => 'A liberacao pelo banco pode levar ate 24 horas. Depois disso, as atualizacoes acontecem em segundo plano e nao sao imediatas.',
            'account' => $this->accountSummary($account),
            'address' => $this->addressForResponse($address),
            'accountHash' => $accountHash,
            'openfinanceLink' => $account->openfinance_link,
        ], 201);
    }

    public function showRemoteAccount(Request $request, string $account): JsonResponse
    {
        if ($response = $this->configurationProblem()) {
            return $response;
        }

        $financialAccount = $this->resolveOpenFinanceAccount($request, $account);
        $document = $this->documentForProvider($request->user());

        try {
            $providerResponse = $this->providerRequest(
                'get',
                'account/'.rawurlencode((string) $financialAccount->openfinance_account_hash),
                $this->payerHeaders($document),
            );
            $this->logProviderResponse('show_account', $providerResponse);
        } catch (ConnectionException $exception) {
            Log::warning('OpenFinance show account connection failed.', [
                'user_id' => $request->user()->id,
                'financial_account_id' => $financialAccount->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Nao foi possivel conectar com a TecnoSpeed agora.',
            ], 504);
        }

        if (! $providerResponse->successful()) {
            return $this->providerFailure($providerResponse, 'Nao foi possivel consultar a conta Open Finance.');
        }

        $remoteAccount = data_get($providerResponse->json() ?? [], 'accounts', []);
        $this->refreshOpenFinanceIdentifiers($financialAccount, is_array($remoteAccount) ? $remoteAccount : []);

        return response()->json([
            'account' => $financialAccount->refresh(),
            'remoteAccount' => $remoteAccount,
        ]);
    }

    public function createStatementProtocol(Request $request): JsonResponse
    {
        if ($response = $this->configurationProblem()) {
            return $response;
        }

        $today = $request->boolean('today');
        $validated = $request->validate([
            'accountId' => ['required', 'uuid'],
            'dateStart' => [$today ? 'nullable' : 'required', 'date_format:Y-m-d'],
            'dateEnd' => [$today ? 'nullable' : 'required', 'date_format:Y-m-d', 'after_or_equal:dateStart'],
            'today' => ['nullable', 'boolean'],
        ]);

        $financialAccount = $this->resolveOpenFinanceAccount($request, $validated['accountId']);
        $document = $this->documentForProvider($request->user());

        $payload = [
            'today' => $today,
            'accountHash' => $financialAccount->openfinance_account_hash,
        ];

        if (! $today) {
            $payload['dateStart'] = $validated['dateStart'];
            $payload['dateEnd'] = $validated['dateEnd'];
        }

        Log::info('OpenFinance statement protocol requested.', [
            'user_id' => $request->user()->id,
            'financial_account_id' => $financialAccount->id,
            'account_hash' => $this->maskIdentifier((string) $financialAccount->openfinance_account_hash),
            'today' => $today,
        ]);

        try {
            $providerResponse = $this->providerRequest(
                'post',
                'statement/openfinance',
                $this->payerHeaders($document),
                $payload,
            );
            $this->logProviderResponse('create_statement_protocol', $providerResponse);
        } catch (ConnectionException $exception) {
            Log::warning('OpenFinance statement protocol connection failed.', [
                'user_id' => $request->user()->id,
                'financial_account_id' => $financialAccount->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Nao foi possivel conectar com a TecnoSpeed agora.',
            ], 504);
        }

        if (! $providerResponse->successful()) {
            return $this->providerFailure($providerResponse, 'Nao foi possivel solicitar o extrato Open Finance.');
        }

        $body = $providerResponse->json() ?? [];
        $data = $financialAccount->data ?? [];
        $data['openfinance']['lastStatementProtocol'] = [
            'uniqueId' => data_get($body, 'uniqueId'),
            'status' => data_get($body, 'status'),
            'requestedAt' => now()->toIso8601String(),
        ];
        $financialAccount->forceFill(['data' => $data])->save();

        return response()->json($body, $providerResponse->status());
    }

    public function showStatementResult(Request $request, string $uniqueId): JsonResponse
    {
        if ($response = $this->configurationProblem()) {
            return $response;
        }

        $validated = validator(
            ['uniqueId' => $uniqueId],
            ['uniqueId' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]+$/']],
        )->validate();

        $document = $this->documentForProvider($request->user());

        Log::info('OpenFinance statement result requested.', [
            'user_id' => $request->user()->id,
            'unique_id' => $this->maskIdentifier($validated['uniqueId']),
        ]);

        try {
            $providerResponse = $this->providerRequest(
                'get',
                'statement/openfinance/'.rawurlencode($validated['uniqueId']),
                $this->payerHeaders($document),
            );
            $this->logProviderResponse('show_statement_result', $providerResponse);
        } catch (ConnectionException $exception) {
            Log::warning('OpenFinance statement result connection failed.', [
                'user_id' => $request->user()->id,
                'unique_id' => $this->maskIdentifier($validated['uniqueId']),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Nao foi possivel conectar com a TecnoSpeed agora.',
            ], 504);
        }

        if (! $providerResponse->successful()) {
            return $this->providerFailure($providerResponse, 'Nao foi possivel consultar o resultado do extrato Open Finance.');
        }

        return response()->json($providerResponse->json() ?? [], $providerResponse->status());
    }

    /**
     * @return array<string, mixed>
     */
    private function validateConnectRequest(Request $request): array
    {
        return $request->validate([
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
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'zipcode' => ['required', 'string', 'max:20'],
            'street' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['required', 'string', 'max:255'],
            'addressNumber' => ['required', 'string', 'max:30'],
            'addressComplement' => ['nullable', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2'],
            'city' => ['required', 'string', 'max:255'],
            'bankCode' => ['required', 'string', 'regex:/^\d{3}$/'],
            'bankName' => ['nullable', 'string', 'max:255'],
            'agency' => ['required', 'string', 'regex:/^\d{1,10}$/'],
            'agencyDigit' => ['nullable', 'string', 'max:2', 'regex:/^[0-9Xx]{0,2}$/'],
            'accountNumber' => ['required', 'string', 'regex:/^\d{1,20}$/'],
            'accountNumberDigit' => ['nullable', 'string', 'max:2', 'regex:/^[0-9Xx]{0,2}$/'],
        ]);
    }

    private function resolveUserDocument(User $user, ?string $requestDocument): string
    {
        $document = BrazilDocument::normalize($requestDocument);

        if ($document !== null) {
            $this->ensureCpfCnpjIsAvailable($document, $user->id);
            $user->forceFill(['cpf_cnpj' => $document])->save();
            $user->refresh();
        }

        return $this->documentForProvider($user);
    }

    private function documentForProvider(User $user): string
    {
        $document = BrazilDocument::normalize($user->cpf_cnpj);

        if ($document === null || ! BrazilDocument::isValid($document)) {
            throw ValidationException::withMessages([
                'cpfCnpj' => ['Informe um CPF/CNPJ valido no perfil antes de usar o Open Finance.'],
            ]);
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function storeOpenFinanceAddress(User $user, array $validated): UserAddress
    {
        $address = UserAddress::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'type' => self::ADDRESS_TYPE,
            ],
            $this->addressAttributes($validated),
        );

        Log::info('OpenFinance address stored.', [
            'user_id' => $user->id,
            'address_id' => $address->id,
            'state' => $address->state,
            'city' => $address->city,
        ]);

        return $address;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function addressAttributes(array $validated): array
    {
        return [
            'zipcode' => preg_replace('/\D+/', '', (string) $validated['zipcode']),
            'street' => $this->nullableTrim($validated['street'] ?? null),
            'neighborhood' => trim((string) $validated['neighborhood']),
            'address_number' => trim((string) $validated['addressNumber']),
            'address_complement' => $this->nullableTrim($validated['addressComplement'] ?? null),
            'state' => strtoupper(trim((string) $validated['state'])),
            'city' => trim((string) $validated['city']),
        ];
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

    private function ensureCpfCnpjIsAvailable(string $cpfCnpj, int $ignoreUserId): void
    {
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
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payerPayload(User $user, string $document, array $validated): array
    {
        return $this->withoutBlankValues([
            'name' => $validated['name'] ?? $user->name,
            'cpfCnpj' => $document,
            'street' => $validated['street'] ?? null,
            'neighborhood' => $validated['neighborhood'],
            'addressNumber' => $validated['addressNumber'],
            'addressComplement' => $validated['addressComplement'] ?? null,
            'zipcode' => preg_replace('/\D+/', '', (string) $validated['zipcode']),
            'state' => strtoupper((string) $validated['state']),
            'city' => $validated['city'],
            'statementActived' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function accountPayload(array $validated): array
    {
        return $this->withoutBlankValues([
            'bankCode' => $validated['bankCode'],
            'agency' => $validated['agency'],
            'agencyDigit' => strtoupper((string) ($validated['agencyDigit'] ?? '')),
            'accountNumber' => $validated['accountNumber'],
            'accountNumberDigit' => strtoupper((string) ($validated['accountNumberDigit'] ?? '')),
            'accountDac' => strtoupper((string) ($validated['accountNumberDigit'] ?? '')),
            'accountPayment' => false,
            'webservice' => false,
            'recipientNotification' => false,
            'statementActived' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>|null  $payload
     */
    private function providerRequest(
        string $method,
        string $path,
        array $headers,
        ?array $payload = null,
    ): ClientResponse {
        $url = $this->baseUrl().ltrim($path, '/');
        $request = Http::withHeaders($headers)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(30);

        return match (strtolower($method)) {
            'get' => $request->get($url),
            'put' => $request->put($url, $payload ?? []),
            default => $request->post($url, $payload ?? []),
        };
    }

    /**
     * @return array<string, string>
     */
    private function softwareHouseHeaders(): array
    {
        return [
            'tokensh' => (string) config('openfinance.tokensh'),
            'cnpjsh' => (string) config('openfinance.cnpjsh'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function payerHeaders(string $document): array
    {
        return [
            ...$this->softwareHouseHeaders(),
            'payercpfcnpj' => $document,
        ];
    }

    private function configurationProblem(): ?JsonResponse
    {
        if (
            trim((string) config('openfinance.url')) === ''
            || trim((string) config('openfinance.tokensh')) === ''
            || trim((string) config('openfinance.cnpjsh')) === ''
        ) {
            Log::error('OpenFinance configuration is incomplete.');

            return response()->json([
                'message' => 'A integracao Open Finance nao esta configurada.',
            ], 503);
        }

        return null;
    }

    private function baseUrl(): string
    {
        $url = trim((string) config('openfinance.url'));

        return str_ends_with($url, '/') ? $url : $url.'/';
    }

    private function providerFailure(ClientResponse $response, string $fallbackMessage): JsonResponse
    {
        $body = $response->json() ?? [];
        $message = data_get($body, 'message')
            ?: data_get($body, 'error')
            ?: data_get($body, 'errors.0.message')
            ?: $fallbackMessage;

        Log::warning('OpenFinance provider returned a failure.', [
            'status' => $response->status(),
            'body' => $this->compactLogBody($body ?: $response->body()),
        ]);

        return response()->json([
            'message' => $message,
            'providerStatus' => $response->status(),
            'errors' => data_get($body, 'errors'),
        ], $response->serverError() ? 502 : 422);
    }

    private function logProviderResponse(string $operation, ClientResponse $response): void
    {
        Log::debug('OpenFinance provider response.', [
            'operation' => $operation,
            'status' => $response->status(),
            'body' => $this->compactLogBody($response->json() ?? $response->body()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasInternalCode(array $data, int $code): bool
    {
        if ((int) data_get($data, 'errors.internalCode') === $code) {
            return true;
        }

        $errors = data_get($data, 'errors', []);

        if (is_array($errors)) {
            foreach ($errors as $error) {
                if (is_array($error) && (int) ($error['internalCode'] ?? 0) === $code) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function firstAccountFromResponse(array $body): array
    {
        $account = data_get($body, 'accounts.0');

        if (is_array($account)) {
            return $account;
        }

        if (array_is_list($body) && isset($body[0]) && is_array($body[0])) {
            return $body[0];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $accountData
     */
    private function storeOpenFinanceAccount(
        User $user,
        array $validated,
        array $accountData,
        string $accountHash,
    ): FinancialAccount {
        $account = FinancialAccount::query()
            ->where('user_id', $user->id)
            ->where('openfinance_account_hash', $accountHash)
            ->first() ?? new FinancialAccount;

        $bankName = trim((string) ($validated['bankName'] ?? ''));
        $name = $bankName !== ''
            ? $bankName
            : 'Conta Open Finance '.$validated['bankCode'];
        $openfinanceId = data_get($accountData, 'openfinanceId');
        $openfinanceLink = data_get($accountData, 'openfinanceLink');

        $data = $account->data ?? [];
        $data['openfinance'] = array_merge($data['openfinance'] ?? [], [
            'bankCode' => $validated['bankCode'],
            'bankName' => $bankName !== '' ? $bankName : null,
            'agency' => $validated['agency'],
            'agencyDigit' => strtoupper((string) ($validated['agencyDigit'] ?? '')),
            'accountNumberLast4' => $this->last4($validated['accountNumber']),
            'accountNumberDigit' => strtoupper((string) ($validated['accountNumberDigit'] ?? '')),
            'statementActived' => true,
            'updatedAt' => now()->toIso8601String(),
        ]);

        $account->fill([
            'user_id' => $user->id,
            'type' => 'bank',
            'subtype' => 'openfinance',
            'name' => $name,
            'marketing_name' => $bankName !== '' ? $bankName : null,
            'owner' => $user->name,
            'number_last4' => $this->last4($validated['accountNumber']),
            'balance' => $account->exists ? $account->balance : 0,
            'currency' => 'BRL',
            'data' => $data,
            'openfinance_account_hash' => $accountHash,
            'openfinance_id' => $openfinanceId ?: $account->openfinance_id,
            'openfinance_link' => $openfinanceLink ?: $account->openfinance_link,
            'openfinance_status' => $openfinanceId ? 'active' : 'authorization_pending',
            'openfinance_synced_at' => now(),
            'openfinance_statement_status' => $account->openfinance_statement_status ?: 'WAITING_AUTHORIZATION',
            'openfinance_statement_error' => 'As atualizacoes acontecem em segundo plano e nao sao imediatas.',
            'openfinance_next_statement_at' => $openfinanceId ? now() : now()->addHour(),
        ]);

        $account->save();

        return $account->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function accountSummary(FinancialAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'bankCode' => data_get($account->data, 'openfinance.bankCode'),
            'bankName' => data_get($account->data, 'openfinance.bankName') ?: $account->marketing_name ?: $account->name,
            'numberLast4' => $account->number_last4,
            'balance' => $account->balance,
            'currency' => $account->currency,
            'openfinanceStatus' => $account->openfinance_status,
            'statementStatus' => $account->openfinance_statement_status,
            'statementError' => $account->openfinance_statement_error,
            'openfinanceLink' => $account->openfinance_link,
            'syncedAt' => $account->openfinance_synced_at?->toIso8601String(),
            'nextStatementAt' => $account->openfinance_next_statement_at?->toIso8601String(),
        ];
    }

    private function resolveOpenFinanceAccount(Request $request, string $accountId): FinancialAccount
    {
        $account = FinancialAccount::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $accountId)
            ->firstOrFail();

        if (! filled($account->openfinance_account_hash)) {
            throw ValidationException::withMessages([
                'accountId' => ['Esta conta nao esta vinculada ao Open Finance.'],
            ]);
        }

        return $account;
    }

    /**
     * @param  array<string, mixed>  $remoteAccount
     */
    private function refreshOpenFinanceIdentifiers(FinancialAccount $account, array $remoteAccount): void
    {
        $openfinanceId = data_get($remoteAccount, 'openfinanceId');
        $openfinanceLink = data_get($remoteAccount, 'openfinanceLink');

        $account->forceFill([
            'openfinance_id' => $openfinanceId ?: $account->openfinance_id,
            'openfinance_link' => $openfinanceLink ?: $account->openfinance_link,
            'openfinance_status' => $openfinanceId ? 'active' : $account->openfinance_status,
            'openfinance_synced_at' => now(),
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withoutBlankValues(array $values): array
    {
        return array_filter(
            $values,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function compactLogBody(mixed $body): mixed
    {
        if (is_array($body) && count($body, COUNT_RECURSIVE) > 80) {
            return [
                'truncated' => true,
                'keys' => array_keys($body),
            ];
        }

        return $this->sanitizeForLog($body);
    }

    private function sanitizeForLog(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $clean = [];

            foreach ($value as $childKey => $childValue) {
                $clean[$childKey] = $this->sanitizeForLog($childValue, (string) $childKey);
            }

            return $clean;
        }

        $normalizedKey = strtolower((string) $key);

        if (in_array($normalizedKey, ['tokensh', 'token', 'clientsecret', 'clientkey', 'clientid'], true)) {
            return '[redacted]';
        }

        if (in_array($normalizedKey, ['openfinancelink', 'openfinance_link'], true)) {
            return '[redacted-url]';
        }

        if (str_contains($normalizedKey, 'cpfcnpj') || str_contains($normalizedKey, 'payercpfcnpj')) {
            return BrazilDocument::mask((string) $value);
        }

        if (str_contains($normalizedKey, 'accountnumber')) {
            return $this->maskAccountNumber((string) $value);
        }

        if (str_contains($normalizedKey, 'accounthash')) {
            return $this->maskIdentifier((string) $value);
        }

        return $value;
    }

    private function maskAccountNumber(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';

        if ($digits === '') {
            return '[empty]';
        }

        return str_repeat('*', max(0, strlen($digits) - 4)).substr($digits, -4);
    }

    private function maskIdentifier(string $value): string
    {
        $value = trim($value);

        if (strlen($value) <= 8) {
            return substr($value, 0, 2).'***';
        }

        return substr($value, 0, 4).'...'.substr($value, -4);
    }

    private function last4(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';

        return $digits !== '' ? substr($digits, -4) : null;
    }
}
