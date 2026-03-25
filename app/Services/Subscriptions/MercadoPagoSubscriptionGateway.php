<?php

namespace App\Services\Subscriptions;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MercadoPagoSubscriptionGateway
{
    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    public function createPendingPreapproval(User $user, array $plan, string $externalReference): array
    {
        $payload = [
            'reason' => $plan['reason'],
            'external_reference' => $externalReference,
            'payer_email' => $user->email,
            'auto_recurring' => [
                'frequency' => $plan['frequency'],
                'frequency_type' => $plan['frequency_type'],
                'transaction_amount' => $plan['amount'],
                'currency_id' => $plan['currency_id'],
            ],
            'back_url' => config('subscriptions.back_url'),
            'status' => 'pending',
        ];

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
     * @return array<string, mixed>
     */
    public function fetchPreapproval(string $preapprovalId): array
    {
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
