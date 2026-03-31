<?php

namespace App\Services\Subscriptions;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class MercadoPagoSubscriptionGateway
{
    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    public function createPendingPreapproval(
        array $plan,
        string $externalReference,
        string $payerEmail,
        ?string $cardTokenId = null,
        ?string $deviceSessionId = null,
    ): array {
        Log::info('5. Entrou em MercadoPagoSubscriptionGateway@createPendingPreapproval', [
            'plan' => $plan['code'] ?? null,
            'external_reference' => $externalReference,
        ]);

        $payload = [
            'reason' => $plan['reason'],
            'external_reference' => $externalReference,
            'payer_email' => $payerEmail,
            'auto_recurring' => [
                'frequency' => $plan['frequency'],
                'frequency_type' => $plan['frequency_type'],
                'transaction_amount' => $plan['amount'],
                'currency_id' => $plan['currency_id'],
            ],
            'back_url' => config('subscriptions.back_url'),
            'status' => 'pending',
        ];

        if (!empty($cardTokenId)) {
            $payload['card_token_id'] = $cardTokenId;
        }

        if (!empty($deviceSessionId)) {
            $payload['device_session_id'] = $deviceSessionId;
        }

        $notificationUrl = trim((string) config('subscriptions.notification_url', ''));
        if ($notificationUrl !== '') {
            $payload['notification_url'] = $notificationUrl;
        }

        return $this->request()
            ->withHeaders([
                'X-Idempotency-Key' => (string) Str::uuid(),
            ])
            ->post('/preapproval', $payload)
            ->throw()
            ->json();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createPixPayment(array $data): array
    {
        Log::info('5. Entrou em MercadoPagoSubscriptionGateway@createPixPayment', [
            'external_reference' => $data['external_reference'] ?? null,
            'transaction_amount' => $data['transaction_amount'] ?? null,
        ]);

        $payload = [
            'transaction_amount' => $data['transaction_amount'],
            'currency_id' => $data['currency_id'] ?? 'BRL',
            'payment_method_id' => 'pix',
            'description' => $data['description'] ?? $data['external_reference'] ?? 'Dinfy subscription',
            'external_reference' => $data['external_reference'],
            'payer' => [
                'email' => $data['payer_email'] ?? null,
            ],
        ];

        if (!empty($data['notification_url'])) {
            $payload['notification_url'] = $data['notification_url'];
        }

        if (!empty($data['expires_at'])) {
            $payload['date_of_expiration'] = Carbon::parse($data['expires_at'])->toIso8601String();
        } else {
            $payload['date_of_expiration'] = Carbon::now()->addDays(3)->toIso8601String();
        }

        return $this->request()
            ->withHeaders([
                'X-Idempotency-Key' => (string) Str::uuid(),
            ])
            ->post('/v1/payments', $payload)
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchPreapproval(string $preapprovalId): array
    {
        Log::info('5. Entrou em MercadoPagoSubscriptionGateway@fetchPreapproval', [
            'preapproval_id' => $preapprovalId,
        ]);

        $searchResponse = $this->request()
            ->get('/preapproval/search', [
                'id' => $preapprovalId,
            ])
            ->throw()
            ->json();

        $result = Arr::first(Arr::get($searchResponse, 'results', []));
        if (is_array($result)) {
            return $result;
        }

        $directResponse = $this->request()
            ->get('/preapproval/' . urlencode($preapprovalId))
            ->throw()
            ->json();

        if (!is_array($directResponse)) {
            throw new RuntimeException('Mercado Pago returned an invalid preapproval payload.');
        }

        return $directResponse;
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelPreapproval(string $preapprovalId): array
    {
        Log::info('6. Entrou em MercadoPagoSubscriptionGateway@cancelPreapproval', [
            'preapproval_id' => $preapprovalId,
        ]);

        return $this->request()
            ->put('/preapproval/' . urlencode($preapprovalId), [
                'status' => 'canceled',
            ])
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelPayment(string $paymentId): array
    {
        Log::info('6. Entrou em MercadoPagoSubscriptionGateway@cancelPayment', [
            'payment_id' => $paymentId,
        ]);

        return $this->request()
            ->put('/v1/payments/' . urlencode($paymentId), [
                'status' => 'canceled',
            ])
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchPayment(string $paymentId): array
    {
        Log::info('5. Entrou em MercadoPagoSubscriptionGateway@fetchPayment', [
            'payment_id' => $paymentId,
        ]);

        return $this->request()
            ->get('/v1/payments/' . urlencode($paymentId))
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchAuthorizedPayment(string $authorizedPaymentId): array
    {
        Log::info('5. Entrou em MercadoPagoSubscriptionGateway@fetchAuthorizedPayment', [
            'authorized_payment_id' => $authorizedPaymentId,
        ]);

        return $this->request()
            ->get('/authorized_payments/' . urlencode($authorizedPaymentId))
            ->throw()
            ->json();
    }

    public function hasWebhookSecret(): bool
    {
        return trim((string) config('services.mercadopago.webhook_secret', '')) !== '';
    }

    public function isValidWebhookSignature(Request $request): bool
    {
        $secret = trim((string) config('services.mercadopago.webhook_secret', ''));
        if ($secret === '') {
            return true;
        }

        $signatureHeader = trim((string) $request->header('x-signature', ''));
        $requestId = trim((string) $request->header('x-request-id', ''));
        if ($signatureHeader === '' || $requestId === '') {
            return false;
        }

        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, null);

                return [
                    trim((string) $key) => trim((string) $value),
                ];
            });

        $ts = (string) ($parts->get('ts') ?? '');
        $hash = (string) ($parts->get('v1') ?? '');
        if ($ts === '' || $hash === '') {
            return false;
        }

        $queryParams = $request->query();
        $dataId = '';

        if (is_array($queryParams) && array_key_exists('data.id', $queryParams)) {
            $dataId = (string) $queryParams['data.id'];
        }

        $manifestParts = [];

        if ($dataId !== '') {
            $manifestParts[] = 'id:' . strtolower($dataId);
        }

        $manifestParts[] = 'request-id:' . $requestId;
        $manifestParts[] = 'ts:' . $ts;

        $manifest = implode(';', $manifestParts) . ';';

        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $hash);
    }

    private function request(): PendingRequest
    {
        $token = trim((string) config('services.mercadopago.access_token', ''));
        if ($token === '') {
            throw new RuntimeException('Mercado Pago access token is not configured.');
        }

        return Http::baseUrl((string) config('services.mercadopago.base_url'))
            ->acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout(15);
    }
}
