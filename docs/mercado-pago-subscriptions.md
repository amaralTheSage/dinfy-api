# Mercado Pago Subscriptions

## Current model

Dinfy now uses its own subscription lifecycle and Mercado Pago only to issue PIX charges for each subscription period.

- The user chooses `monthly` or `yearly`.
- The backend creates a PIX payment in Mercado Pago via `POST /v1/payments`.
- The backend stores the subscription locally in `user_subscriptions`.
- Each PIX charge is stored in `subscription_invoices`.
- The Flutter app opens the Mercado Pago hosted payment page from `ticket_url`.

This keeps the QR code on Mercado Pago's own checkout page instead of rendering it inside Dinfy.

## Required environment variables

```env
MERCADO_PAGO_ACCESS_TOKEN=your_access_token
MERCADO_PAGO_NOTIFICATION_URL=https://api.dinfy.app/api/mercado-pago/webhook
DINFY_APP_SUBSCRIPTION_RETURN_URL=dinfy://subscription
MERCADO_PAGO_MONTHLY_PRICE=19.90
MERCADO_PAGO_YEARLY_PRICE=97.00
```

## Main checkout flow

1. Flutter calls `POST /api/subscriptions/checkout` with the selected plan.
2. Backend uses the authenticated user's email, creates a PIX payment in Mercado Pago and stores the local subscription.
3. Backend returns the local subscription plus `checkout_url`, which should point to Mercado Pago's hosted page.
4. Flutter opens `checkout_url` in the external browser.
5. The buyer completes the payment on Mercado Pago's page, where the QR code is shown.
6. Mercado Pago sends webhook updates to `POST /api/mercado-pago/webhook`.
7. Backend syncs the payment, updates the subscription period and records the invoice history.

## API contract

### Create checkout

`POST /api/subscriptions/checkout`

Request:

```json
{
  "plan": "monthly"
}
```

Optional request fields:

- `payer_email`: overrides the authenticated user's email if needed.
- `payment_method`: only `pix` is accepted.

Response:

```json
{
  "subscription": {
    "id": 1,
    "plan": "monthly",
    "status": "pending",
    "provider": "pix",
    "checkout_url": "https://www.mercadopago.com.br/payments/checkout-v1?...",
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

- `checkout_url` is the preferred way to continue the payment flow.
- `latest_invoice` exists for auditing and history, not for rendering your own checkout UI.
- Card tokenization and hosted Dinfy checkout pages are no longer part of this flow.

### Get current subscription

`GET /api/subscriptions/current?sync=1`

- Returns the current local subscription or `null`.
- With `sync=1`, the backend refreshes pending PIX payments from Mercado Pago before responding.

### Cancel current subscription

`POST /api/subscriptions/current/cancel`

- Pending PIX payments are canceled in Mercado Pago when a payment already exists.
- Active local PIX subscriptions are canceled locally, stopping future renewals on Dinfy's side.

## Webhooks

`POST /api/mercado-pago/webhook`

Supported topics:

- `payment`
- `subscription_preapproval`
- `subscription_authorized_payment`

For the current PIX-first flow, `payment` is the main topic used to confirm approval, cancellation or expiration of the charge.

## Frontend requirement

The Flutter app must:

1. Call `POST /api/subscriptions/checkout`.
2. Read `subscription.checkout_url`.
3. Open that URL in the external browser.

The app should not render its own PIX QR code screen for checkout.
