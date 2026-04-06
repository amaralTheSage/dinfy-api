# Mercado Pago Subscriptions

## Current model

Dinfy owns the subscription lifecycle locally.
Mercado Pago is used only to create and update PIX charges.

- The backend creates a PIX payment through `POST /v1/payments`.
- The subscription record lives in `user_subscriptions`.
- Each PIX charge lives in `subscription_invoices`.
- Webhooks sync payment status back into the local subscription.

Legacy Mercado Pago preapproval ids can still exist on older records, so the backend keeps compatibility for those webhook topics, but new checkouts are PIX-first.

## Required environment variables

```env
MERCADO_PAGO_ACCESS_TOKEN=your_access_token
MERCADO_PAGO_NOTIFICATION_URL=https://api.dinfy.app/api/mercado-pago/webhook
MERCADO_PAGO_MONTHLY_PRICE=19.90
MERCADO_PAGO_YEARLY_PRICE=97.00
```

## Main checkout flow

1. Flutter calls `POST /api/subscriptions/checkout` with the selected plan and `payer_document`.
2. Backend creates a PIX payment in Mercado Pago and stores the local subscription.
3. Backend returns the local subscription plus the latest invoice payload.
4. Flutter uses `subscription.latest_invoice` to show the PIX QR code or copy-and-paste code.
5. Mercado Pago sends webhook updates to `POST /api/mercado-pago/webhook`.
6. Backend syncs payment approval, cancellation, expiration, and invoice history.

## API contract

### Create checkout

`POST /api/subscriptions/checkout`

Request:

```json
{
  "plan": "monthly",
  "payer_document": "12345678909"
}
```

Optional request fields:

- `payer_email`: overrides the authenticated user's email if needed.
- `payment_method`: legacy input, but only `pix` is accepted.

Response:

```json
{
  "subscription": {
    "id": 1,
    "plan": "monthly",
    "status": "pending",
    "provider": "pix",
    "checkout_url": null,
    "mercado_pago_payment_id": "123456789",
    "latest_payment_status": "pending",
    "next_payment_at": null,
    "latest_invoice": {
      "id": 10,
      "provider_payment_id": "123456789",
      "status": "pending",
      "status_detail": "pending_waiting_payment",
      "expires_at": "2026-04-03T00:00:00Z",
      "paid_at": null,
      "qr_code": "000201...",
      "qr_code_base64": "data:image/png;base64,..."
    }
  }
}
```

Important notes:

- `checkout_url` is nullable in the PIX-first flow and should not be required by the app.
- `latest_invoice` is the canonical place for PIX checkout data.
- Card tokenization and hosted Dinfy checkout pages are not part of the current flow.

### Get current subscription

`GET /api/subscriptions/current?sync=1`

- Returns the current local subscription or `null`.
- With `sync=1`, pending PIX payments are refreshed from Mercado Pago before responding.

### Cancel current subscription

`POST /api/subscriptions/current/cancel`

- Pending PIX payments are canceled in Mercado Pago when a payment already exists.
- Active local PIX subscriptions are canceled locally, stopping future renewals on Dinfy's side.

## Webhooks

`POST /api/mercado-pago/webhook`

Supported topics:

- `payment`
- `subscription_preapproval` (legacy compatibility)
- `subscription_authorized_payment` (legacy compatibility)

For the current PIX-first flow, `payment` is the main topic used to confirm approval, cancellation, or expiration of the charge.
