<?php

namespace App\Services\Subscriptions;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class MercadoPagoSubscriptionGateway
{
    private const LOCAL_CANCELED_STATUS = 'canceled';

    private const MERCADO_PAGO_PAYMENT_CANCELLATION_STATUS = 'cancelled';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createPixPayment(array $data): array
    {
        Log::info('MercadoPagoSubscriptionGateway@createPixPayment was hit.');

        $payer = [
            'email' => $data['payer_email'] ?? null,
        ];

        if (! empty($data['payer_first_name'])) {
            $payer['first_name'] = $data['payer_first_name'];
        }

        if (! empty($data['payer_last_name'])) {
            $payer['last_name'] = $data['payer_last_name'];
        }

        if (! empty($data['payer_identification_number'])) {
            $payer['identification'] = [
                'type' => $data['payer_identification_type'] ?? 'CPF',
                'number' => $data['payer_identification_number'],
            ];
        }

        $payload = [
            'transaction_amount' => $data['transaction_amount'],
            'payment_method_id' => 'pix',
            'description' => $data['description'] ?? $data['external_reference'] ?? 'Dinfy subscription',
            'external_reference' => $data['external_reference'],
            'payer' => $payer,
        ];

        if (! empty($data['notification_url'])) {
            $payload['notification_url'] = $data['notification_url'];
        }

        if (! empty($data['expires_at'])) {
            $payload['date_of_expiration'] = Carbon::parse($data['expires_at'])->format('Y-m-d\TH:i:s.vP');
        }

        try {
            return $this->normalizePayloadStatuses($this->request()
                ->withHeaders([
                    'X-Idempotency-Key' => (string) Str::uuid(),
                ])
                ->post('/v1/payments', $payload)
                ->throw()
                ->json());
        } catch (RequestException $exception) {
            Log::error('Mercado Pago payment request failed.');

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelPayment(string $paymentId): array
    {
        Log::info('MercadoPagoSubscriptionGateway@cancelPayment was hit.');

        return $this->normalizePayloadStatuses($this->request()
            ->put('/v1/payments/'.urlencode($paymentId), [
                'status' => self::MERCADO_PAGO_PAYMENT_CANCELLATION_STATUS,
            ])
            ->throw()
            ->json());
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchPayment(string $paymentId): array
    {
        Log::info('MercadoPagoSubscriptionGateway@fetchPayment was hit.');

        return $this->normalizePayloadStatuses($this->request()
            ->get('/v1/payments/'.urlencode($paymentId))
            ->throw()
            ->json());
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
            $manifestParts[] = 'id:'.strtolower($dataId);
        }

        $manifestParts[] = 'request-id:'.$requestId;
        $manifestParts[] = 'ts:'.$ts;

        $manifest = implode(';', $manifestParts).';';

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

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayloadStatuses(array $payload): array
    {
        array_walk_recursive($payload, function (mixed &$value, string|int $key): void {
            if ($key !== 'status' || ! is_string($value)) {
                return;
            }

            $value = $this->normalizeStatus($value);
        });

        return $payload;
    }

    private function normalizeStatus(string $status): string
    {
        return strtolower(trim($status)) === self::MERCADO_PAGO_PAYMENT_CANCELLATION_STATUS
            ? self::LOCAL_CANCELED_STATUS
            : $status;
    }
}
