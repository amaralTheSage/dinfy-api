# Mercado Pago Subscriptions

## Current model

Dinfy now defaults to Mercado Pago subscriptions with:

- associated plan (`preapproval_plan_id`)
- authorized payment (`status=authorized`)

This matches the product catalog better because Dinfy has two fixed offerings:

- `monthly`
- `yearly`

## Required environment variables

Configure one Mercado Pago associated plan for each Dinfy plan:

```env
MERCADO_PAGO_MONTHLY_PLAN_ID=your_monthly_preapproval_plan_id
MERCADO_PAGO_YEARLY_PLAN_ID=your_yearly_preapproval_plan_id
```

## Backend contract

`POST /api/subscriptions/checkout`

Request body:

```json
{
  "plan": "monthly",
  "card_token_id": "CARD_TOKEN"
}
```

Behavior:

- uses the authenticated Dinfy user's email as `payer_email`
- creates the subscription in Mercado Pago with `preapproval_plan_id`
- stores the returned `mercado_pago_preapproval_id`
- updates the local subscription status from the Mercado Pago response

Notes:

- `card_token_id` is required for `subscription_authorized`
- `payer_email` is only required if a plan is explicitly configured with `checkout_mode=subscription_pending`

## Frontend requirement

The Flutter app should no longer redirect users to the Mercado Pago hosted pending checkout for the main subscription flow.

Instead, it should open a page that renders Mercado Pago card tokenization (`Card Payment Brick` or `CardForm`), initialized with the Dinfy user's email, then send the generated `card_token_id` to the API.

Recommended flow:

1. User chooses `monthly` or `yearly`.
2. Frontend opens Dinfy's own payment form.
3. Mercado Pago JS tokenizes the card and returns `card_token_id`.
4. Frontend calls `POST /api/subscriptions/checkout`.
5. Backend creates the authorized subscription linked to the corresponding associated plan.

## Fallback compatibility

The backend still supports `subscription_pending` for any plan configured that way, but this is now treated as fallback behavior instead of the primary architecture.
