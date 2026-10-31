---
name: subscriptions-stripe
description: Stripe store-run subscriptions (PR #3270, decided in #3183): Customer and setup_future_usage on the first payment, card_ref, off-session charge_renewal, Checkout setup page, detach on cancel
module: subscriptions
---

## When does Stripe run subscriptions?
- Always: `pg_payment_stripe_capabilities()` declares `'recurring' => 'store'` with no switch, since Stripe charges a kept card off-session for any account (unlike iyzico, `subscriptions-iyzico.md`). The checkout hook of slice 2 (`pg_subscription_checkout_create()`) picks it up unchanged, later lines included (`subscriptions-later-start.md`).

## How is the card kept on the first payment?
- `authorize()` with `payment['recurring']` (the cart has a priced recurring line) first makes a Customer (`POST /v1/customers`: email, name, metadata order_id and user_id; key `pg-cus-` + order + token), then creates the intent with `customer` and `setup_future_usage: off_session`.
- The Payment Element must be built with the same `setupFutureUsage`, or Stripe refuses the confirmation token: `prepare()` adds `client.setup_future_usage` when `$order['recurring']`; `_eo_hosted_card_html()` sets it from `pg_payment_order_recurring()` (fn/payments.php: active line, `products.recurring`, `order_items.price > 0`, as `submit_order.php` decides); `payment_hosted.js` passes it on.
- `card_ref` = `"<cus_…>|<pm_…>"` (`pg_payment_stripe_card_ref()`, ≤150), answered by `authorize` and the 3-D Secure `complete` from any intent with `setup_future_usage` off_session. Never logged; the checkout seals it.

## What does charge_renewal do?
- Lists the Customer's cards (`GET /v1/customers/{id}/payment_methods`) and charges the newest (a card added on the update page takes over; answered as the new `card_ref` for the caller to re-seal, the old card detached after it paid). List not read → the stored card; list empty → plain failure.
- `POST /v1/payment_intents` `off_session` + `confirm`, the row's amount and currency (base currency when empty), capture always automatic, metadata `subscription_id` only (no `order_id`: the webhook would take it for the first order). Key `pg-renew-` + id, `next_charge_at`, `retry_count`, card, amount: a cut-short cron run repeats its attempt, the next ladder step is a new charge.
- Answers: succeeded → paid, processing → pending; a 4xx refusal → clear failure (`raw.code` = decline code); no answer, 5xx, or an intent not for this row → unclear.
- `authentication_required`: the refused intent is confirmed again with `return_url` (`$row['return_url']` or the site root; key `pg-renew-confirm-`) and answered as redirect to Stripe's confirmation page (`raw.code` authentication_required); the renewal writer moves the row to `past_due` and mails the link (#3183). No page given → plain failure with that code.

## Card update and cancel?
- `update_card($row)`: Checkout Session `mode=setup` for the Customer (card, Link when on), `success_url` = `cancel_url` = the return address, metadata `subscription_id`; key `pg-setup-` + id, customer, address, UTC day. Answers redirect; never unclear. Chosen over the Billing Portal, which needs a portal configuration saved in the Dashboard.
- `subscription_cancel($row)`: detaches every card of the Customer (the stored one when the list cannot be read). Lines of one order share the Customer: the cancel slice calls it only when no other live subscription of that order uses it.

## Who takes the confirmation page's payment?
- `payment_intent.succeeded` carries `subscription_id`, not `order_id`: `notify()` answers it with `raw.subscription_id` and the renewal writer takes it (`subscriptions-renewal.md`).

## How was it verified?
- `tests/payment_stripe_test.php` (stand-in HTTP caller). No Stripe test-mode run: the off-session and confirmation flows follow Stripe's documentation.

Related: commerce/subscriptions.md, commerce/payment-driver-stripe.md, plans/subscriptions-iyzico-stripe.md
