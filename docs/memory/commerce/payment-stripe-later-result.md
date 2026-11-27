---
name: payment-stripe-later-result
description: A Stripe PaymentIntent still processing places the order awaiting payment (card + pi_ id + paid_at 0); payment_intent.succeeded records it, payment_failed alerts; SEPA Direct Debit uses it (PR #12087)
module: payments
---

## When is a Stripe order placed unpaid?
- `authorize()` and the return answer `pending` when the intent is `processing`; `pg_order_checkout_paid_at()` writes `paid_at = 0` when `pg_order_payment_may_pend($method, $transaction_id)` (`includes/fn/ecommerce.php`).
- `pg_order_payment_may_pend()`: a method of `pg_order_pending_payment_methods()` (PayPal Checkout), or `Credit/Debit Card` whose `transaction_id` starts `pi_` (`pg_order_pending_card_transaction_prefix()`). Older card orders (other gateways, classic charges) never read as pending. SQL twin: `pg_order_payment_may_pend_sql($table)`, used by the list filter, the cancel refund owed, reviews.
- No column, no new payment method value: the method stays `Credit/Debit Card`.

## What does the webhook do?
- `payment_intent.succeeded` (metadata `order_id`) → `paid`; `pg_payment_notify_outcome()` (`payment-callback.md`) records it (`mark_paid`, `pg_order_mark_paid()` sets `paid_at` only where it is 0).
- Late `succeeded` for an order already paid → `nothing`; for an order cancelled with `paid_at` 0 → alert "refund … or restore the order". Pinned in `payment_stripe_webhook_test.php`.
- `payment_intent.payment_failed` with metadata `order_id` → `cancelled`, log "Stripe reports that payment … failed (code): nothing was taken. Do not ship it". A placed order: alert, the order is not changed, the operator cancels it (as PayPal DENIED). An `incomplete` order (a declined try at the checkout): `nothing`, no line; this applies to every driver's `cancelled`. With `subscription_id` only it stays the renewal failure (`subscriptions-held-renewal.md`).

## What do staff see?
- `pg_order_payment_pending_label()`: "Payment pending on Stripe" or "… on PayPal" (`pg_order_payment_pending_service()`): the order screen head's Payment pill, the View Order banner title, the print header.
- View Order banner for Stripe: not settled yet, a bank debit can take days and may still fail, wait before shipping. Payment Received asks "Record that Stripe completed the payment for this order?".
- Cancel: refund owed 0 while processing; the plan still tries the driver's cancel when auto refund is on (`pg_order_cancel_payment_plan()` takes the transaction id; a bool still works).

## What does the buyer see?
- `pg_order_payment_confirming_note($order)` (needs `paid_at` and `transaction_id` in the row): "Your payment is being confirmed. We will start on your order once it arrives." for any pending payment (PayPal too).
- Shown under the payment method on the order receipt page, the account's order page (classic and the two templates, `$payment_confirming_note`, class `payment_confirming_note`) and the plain-text order mail.
- The designer's Order View widget shows "Awaiting Payment" and no "Payment Received" timeline event while pending; the template converter maps the note's `if` to the widget.

## SEPA Direct Debit?
- Row `sepa_debit` of `pg_payment_stripe_methods()`: EUR only, `immediate` false, `hold` false (not under Authorize), not recurring, `step` `element`. Help adds "The payment arrives days later …".
- The Payment Element (deferred mode) collects IBAN, name, e-mail and shows the mandate; the ConfirmationToken carries the mandate, so the intent body has no `mandate_data` (pinned). Stripe answers `processing`, no redirect.
- Security policy: nothing added; the element stays in `js.stripe.com` frames.

## Not verified?
- No Stripe call: the first live try (test keys, a SEPA test IBAN, webhook delivery) is the owner's.

Related: commerce/payment-stripe-method-list.md, commerce/payment-driver-stripe.md, commerce/payment-driver-paypal.md
