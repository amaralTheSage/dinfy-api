# Mercado Pago Subscriptions

## Current model

Dinfy now defaults to Mercado Pago subscriptions with:

- associated plan (`preapproval_plan_id`)
- authorized payment (`status=authorized`)
- hosted card tokenization page opened from the Flutter app

This fits the current catalog because Dinfy has two fixed offers:

- `monthly`
- `yearly`

## Required environment variables

```env
MERCADO_PAGO_PUBLIC_KEY=your_public_key
MERCADO_PAGO_MONTHLY_PLAN_ID=your_monthly_preapproval_plan_id
MERCADO_PAGO_YEARLY_PLAN_ID=your_yearly_preapproval_plan_id
DINFY_APP_SUBSCRIPTION_RETURN_URL=dinfy://subscription
```

## Main checkout flow

1. Flutter calls `POST /api/subscriptions/checkout/session`.
2. Backend creates a short-lived checkout session.
3. Flutter opens `checkout_page_url` in the external browser.
4. The hosted page renders Mercado Pago card tokenization with `MercadoPago.js`.
5. The hosted page sends `card_token_id` to `POST /api/subscriptions/checkout/session/{session}/complete`.
6. Backend creates the authorized subscription with the configured associated plan.
7. Backend returns a deep link back to the app: `dinfy://subscription?...`.

## API contract

### Create checkout session

`POST /api/subscriptions/checkout/session`

Request:

```json
{
  "plan": "monthly"
}
```

Response:

```json
{
  "session": {
    "id": "UUID",
    "plan": "monthly",
    "checkout_page_url": "https://api.dinfy.app/subscriptions/checkout/session/UUID",
    "expires_at": "2026-03-25T20:30:00Z"
  }
}
```

### Complete checkout session

`POST /api/subscriptions/checkout/session/{session}/complete`

Request:

```json
{
  "card_token_id": "CARD_TOKEN"
}
```

Behavior:

- uses the Dinfy user's email as `payer_email`
- creates the subscription in Mercado Pago with `preapproval_plan_id`
- stores the returned `mercado_pago_preapproval_id`
- clears the temporary checkout session
- returns `redirect_url` back to the app

### Direct subscription endpoint

`POST /api/subscriptions/checkout` still exists and accepts:

```json
{
  "plan": "monthly",
  "card_token_id": "CARD_TOKEN"
}
```

But this is now the lower-level endpoint used by the hosted checkout completion step, not the main Flutter entrypoint.

## Frontend requirement

The Flutter app should start the flow with the checkout session endpoint. The user must never type `card_token_id` or any Mercado Pago internal identifier.

User flow:

1. User chooses `monthly` or `yearly`.
2. App opens the secure browser page.
3. User fills card data there.
4. Mercado Pago generates the card token in the browser.
5. Browser returns automatically to the app after the subscription is created.

## Fallback compatibility

The backend still supports `subscription_pending` for any plan configured that way, but this is fallback behavior, not the primary architecture.
