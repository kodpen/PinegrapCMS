---
name: order-completion-before-lock
description: Order completion A2d2 (#2937): key code use, gift card redemption and its error mail, affiliate commission rows moved behind the order lock; auto-registration stays with the visitor's request, and why
module: order_completion
---

## What moved behind the lock?
- Run by `pg_order_complete()` right after the order SMS (#2972; before, by `submit_order()` after the call), in this order (`tests/order_completion_test.php` pins it). Inputs from `$values` and the context (`order-completion.md`):
- `pg_order_complete_key_code($key_code, $actor)`: a single-use key code is disabled. `pg_order_key_code($code)` (read only) runs before the lock: the offer code it stands for (`report = offer_code`) is a value of the completing UPDATE.
- `pg_order_complete_gift_cards($order_id, $order_number, $required_amount, $actor, $log_user)`: reads `applied_gift_cards` (codes and the balances the checkout refreshed into `old_balance`), redeems in applied order, removes cards not needed or failed; a shortfall logs and e-mails "Gift card error" to the store (`pg_order_gift_card_error_message()`). Only while `ECOMMERCE_GIFT_CARD` is on.
- `pg_order_complete_commission($order_id, $affiliate_code, $affiliate_rate, $commission, $recurring_products, $user_id)`: the pending `commissions` row (amount > 0) and a `recurring_commission_profiles` row per monthly/yearly commissionable recurring line. Nothing when the rate is null.
- Before the lock, as values of the UPDATE: `pg_order_affiliate_rate()` (approved affiliate's rate, 0 → `AFFILIATE_DEFAULT_COMMISSION_RATE`, none → null), `pg_order_commission()` (pure; less the discount's and gift cards' share, `round()`).
- Pure: `pg_order_commission_rate()` (a lower product limit wins), `pg_order_recurring_commission_start()` (start today or past → +30 / +365 days; else its own date).

## Why behind the lock?
- Before, a second submit of the same checkout used the key code, redeemed the gift cards (twice the money off the card) and wrote a second commission, though the lock then stopped it. Now only the request that completes the order writes them.
- The redemption needs nothing posted: the codes and refreshed balances are in `applied_gift_cards`, so a payment service's message can redeem too. Its figure (`gift_card_discount`) was settled before and the order is accepted even on a shortfall, so the UPDATE does not wait on it.

## Why does auto-registration stay?
- It signs the visitor in (session, device cookie) and keeps the new account in the session for the receipt; its user id is a value of the completing UPDATE and of the commission rows. A repeat finds the account by e-mail (`existing`) and creates nothing twice.
- A payment service's message (A3) has no visitor: a guest's order completed that way opens no account.

## What changed for a visitor?
- The same writes and messages; they now come after the order number and SMS instead of before the UPDATE. Queries are `db_q()` with placeholders; a failed one ends the request (`output_error`).

Related: commerce/order-completion.md, commerce/givex-gift-card.md, plans/order-completion-function.md
