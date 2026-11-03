---
name: store-credit-steps
description: Steps SC1-SC4 of plans/store-credit.md: the customer-bound card model, credit at checkout through applied_gift_cards, points to credit, refunds to credit; claim as [plan:store-credit] SC<n>
---

## Which run side by side?
- Wave 1: SC1 alone (question 1 first). Wave 2: SC2, SC3, SC4 and SC5 at once (checkout, points, refunds, screens: different files; SC5 after question 2). Wave 3: SC6. Wave 4: SC7. SC5-SC7: `store-credit-steps-2.md`.
- S under 400 product lines, M about 1,000. Tests in `tests/store_credit_<topic>_test.php` (pure functions, red first), memory under `commerce/`, texts through `tr_add.php`.

## Which steps are there?
- SC1 (M) Model. `free` after question 1.
  - Migration sub-step (`install_add_column()` ×4, rerun twice). New `includes/fn/store_credit.php`: `pg_credit_kinds()`, `pg_credit_issue($user_id, $amount, $kind, $reason, $expires, $issued_by)` (a `gift_cards` row with a generated code the customer never needs), `pg_credit_balance($user_id)`, `pg_credit_cards($user_id)` (open cards, oldest expiry first), `pg_credit_void($card_id, $reason)`, `pg_credit_pick($cards, $amount)` (pure: which cards cover an amount), `pg_credit_points_value($points)` (pure, minor units, `round()`), the audit-trail lines. Tests: the pick order, the rounding at the smallest unit, a voided card never picked. Memory: `commerce/store-credit-model.md`.

- SC2 (M) Checkout. After SC1.
  - Express order and the classic checkout: for a signed-in customer with a balance, "Use my store credit (<amount>)" ticked by default on the payment step; ticked, the picked cards are written to `applied_gift_cards` for the order exactly as a typed code is (the same insert, balance check and refresh in `submit_order.php`); unticked, the rows are removed; a guest sees nothing. The receipt and order screen name the credit used as they name gift cards; a cancelled order restores the balance through `order-cancel-gift-cards.md`'s path. Parity test: an order without credit renders and totals as before. Memory: `commerce/store-credit-checkout.md`.

- SC3 (S) Points to credit. After SC1.
  - Settings › Ecommerce › Gift Cards & Rewards: "Points are worth" (points per unit of money) and the smallest conversion; My Account: the points balance, "Convert N points" (one UPDATE with a `WHERE user_reward_points >= ?` guard, then `pg_credit_issue()` kind `points`; refused when the guard misses); the reward goal untouched. Memory: `commerce/store-credit-points.md`.

- SC4 (M) Refunds to credit. After SC1.
  - The return refund screen (`order-returns-refund.md`) and the order cancel refund get the choice "Store credit" with an optional bonus percentage (a setting, default 0); the refund is written as today with `refund_method = credit` and the card id in `order_returns.refund_*` (`order-returns-refund-columns.md`), the ERP hand-off unchanged (a refund is a refund), the mail names the credit. Tests on the amount with and without the bonus. Memory: `commerce/store-credit-refunds.md`.
