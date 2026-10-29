---
name: order-completion-from-payment
description: pg_order_complete_from_payment() (A3, #3021; account A3b, #3035): a payment service's message completes an incomplete order the visitor left: why, values, lines, receipt, account, legal documents
module: order_completion
---

## Why does a message complete the order?
- Owner (#2382, 2026-10-27): a payment the service confirms after the visitor left completes the order; the visitor may never return. The visitor's return and the message complete through the same `pg_order_complete()`; the six frozen legacy gateways' code in `submit_order.php` is not touched.

## What calls it?
- PayTR's callback too (`payment-driver-paytr.md`).
- `pg_payment_callback_receive()` when `pg_payment_notify_outcome()` answers `complete` (`payment-callback.md`): the order's own payment, for its total, in the store's currency. Returns `completed` (order number), `nothing` (no longer incomplete, or `pg_order_complete()`'s lock went to another request) or `refused` (reason).

## Where do the values come from?
- The order row as the visitor's redirect left it (`pg_order_payment_started_query()`): payment method, figures, total, payment id; `pg_order_payment_completion_values()` (pure) adds the order date, `paid_at` (`pg_order_checkout_paid_at()`), the commission (`pg_order_commission()`, total without surcharge = `total - surcharge`), the key code's offer code, `checkout_page_id`. The IP, tracking and UTM columns stay as the cart kept them.
- Lines: `pg_order_lines_sql()`, the query `submit_order()` uses too; `pg_order_lines_charged()` (pure) splits them by `pg_order_line_charged_today()`.
- The shipping's minor units the checkout gave up (`pg_order_charges_reachable()`): `SUM(ship_tos.shipping_cost) - orders.shipping`, taken off the last recipient that covers it, as `submit_order()` does.

## What does a payment in installments write? (#3070)
- The receiver passes `$paid` (amount, installments); `pg_payment_paid_totals()` on the total read here gives `total` (+ the difference), `installment_charges`, `payment_installment` over the kept values; the commission stays on the kept total.

## What is refused (alert, nothing written)?
- `payment_method` '' (sent away before #2998 kept the values); an amount that is no longer the order's total (or the total plus an installment difference); no lines, or the lines charged today no longer sum to the kept `subtotal` (the cart changed after the redirect).

## Receipt and account? (#2984)
- `pg_order_checkout_page_row()` reads the page `checkout_page_id` names with `pg_order_checkout_page_sql()` (the query `submit_order()` uses); `pg_order_payment_page_settings()` (pure) gives its receipt and switch, or for no page a plain-text receipt, subject `lang('Order Receipt #')` + number, always sent, no account.
- Switch on and a guest order: `pg_order_auto_register()` links the account with the billing e-mail or opens one; nobody is signed in, and the customer's receipt carries the set password link (`order-completion-account.md`, #3035).

## Legal documents? (#3001)
- `pg_order_legal_acceptance_read()` (pure) decodes `orders.legal_acceptance`; NULL or unreadable: preinfo and contract accepted, kvkk not, the order's IPv4 (`INET_NTOA`), no browser, now. The types are `pg_order_complete()`'s documents; ip, user_agent, at go in `$context['legal_acceptance']` to `pg_legal_documents_store($order_id, $types, $ip, $agent, $at)` (`accepted_at` and the consent rows take `at`; `created_at` stays the store moment).

## After it?
- `pg_erp_auto_invoice($order_id, 'order_paid', true)`, only when this request completed the order. No post-save hook, no recurring profiles of the frozen gateways.

## Tested?
- `tests/order_completion_test.php`, `tests/payment_stripe_webhook_test.php` (pure helpers, the outcome, source assertions). Not run against a database or a real Stripe or PayPal delivery.

Related: commerce/order-completion.md, commerce/order-completion-account.md, commerce/payment-callback.md
