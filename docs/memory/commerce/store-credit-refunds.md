---
name: store-credit-refunds
description: Refunds given as store credit (2026.11.0): the return refund's credit method, the cancelled order's refund dialog, the bonus setting, what is written, the mails
module: store_credit_refunds
---

## Where can a refund become store credit?
- The return's Payment Refund card (`view_return.php`): method `credit` beside driver and manual, offered when the order has a customer account (`pg_credit_refund_possible()`: `orders.user_id` > 0, the user exists, `pg_credit_ready()`). A guest order cannot.
- A cancelled order that still owes money (`refund_status` manual_required, failed, pending): the "Mark refund as processed" dialog of `view_order.php` offers "Give it as store credit" for the same customers; the amount is `pg_order_cancel_refund_owed()['total']` (payment money left and the gift card part), not typed.
- The external API refund keeps `driver` and `manual` (any other method is a 422); `OrderReturn.refund.method` and the event's `refund_method` read `credit` for a credit refund.

## What does the bonus do?
- Settings › Ecommerce › Gift Cards & Rewards: "Bonus on refunds given as store credit" (%), the `config_text` row `credit_refund_bonus` (`pg_credit_refund_parse_bonus()`: dot number, 2 decimals, capped at 100; '' = none, the default).
- The card holds `pg_credit_refund_card_amount($amount, $percent)` = amount + `round(amount * percent / 100)`. The bonus is on the card only: the refunded amount, `order_refunds` and the ERP see the refund without it.

## What is written?
- Return (`pg_return_refund()`, method `credit`, amount > 0): under the return lock, after the move to `refunded`, `pg_credit_refund_issue()` writes a card (kind `refund`, never expiring, reason naming the return and order, issued by the staff member); no card → back to `received`, code `credit`. Then as every refund: `orders.refunded_amount += amount`, `order_refunds` type `return_credit` with `transaction_id` = card id, `order_returns.refund_method = 'credit'`, `refund_reference` = card id, points taken back, audit line, `order.return.refunded` with `refund_method` `credit`. The ERP hand-off is the same as for any refund (a refund is a refund).
- Cancelled order (`pg_credit_refund_cancelled_order()`): `refund_status` → `refunded` guarded by the owing states (a second click finds nothing), then the card; no card puts the status back. `refund_reference = 'store_credit:<card id>'` (`pg_credit_refund_reference()`, read back by `pg_credit_refund_card_id()`; the banner shows "Store credit" and links the card), `refunded_amount += min(amount, payment part)`, an `order_refunds` row type `credit` (`transaction_id` card id), one activity line.

## What does the customer get?
- Return: the refund mail (`pg_return_refund_mail_body()`, `credit` set) adds "added to your account as store credit of <card amount>" instead of the payment sentence.
- Cancelled order: `pg_credit_refund_cancel_mail_body()`, the order number, the refund and the card amount.

## Tests?
- `tests/store_credit_refunds_test.php`: bonus parser, card amount with and without the bonus (rounding, cap), reference, both mails.
