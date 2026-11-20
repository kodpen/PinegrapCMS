---
name: cart-recovery-figures
description: Recovered carts figures (PR #9796): what left, reached, came back, recovered and revenue count; the Visitors section, the dashboard card, GET /reports/cart-recovery
module: cart_recovery_figures
---

## What do the figures count?
- Read from `cart_recovery_touch` only (`cart-recovery-touches.md`), no rollup. `pg_cart_recovery_figures()` is pure, tests in `tests/cart_recovery_figures_test.php`; `pg_cart_recovery_period($from, $to)` reads the rows and names the messages.
- A touched cart belongs to the period of its first touch (`channel <> ''`, `sent_at > 0`).
- `pg_cart_recovery_touch_rows($from, $to, $now)` LEFT JOINs `orders` (status, cancelled_at, total, refunded_amount, order_date) and reads each row with pure `pg_cart_recovery_touch_read()` (PR #<n>, tests in `tests/cart_recovery_figures_test.php`). Every reader of `recovered` and `completed_total` (the figures, the dashboard card, the API, `cart_recovery_weak.php`) takes its rows from it.
- Cancel and refund, architect (2026-11-20, agent channel message 10532): an order `pg_customer_value_order_counts()` does not count (cancelled, or refunded in full) is not recovered and brings no revenue, as on the Customers report; the cart stays left, reached and came back. A partial refund keeps it recovered with revenue `completed_total - refunded_amount`, never below 0. An undone cancel brings the figure back. No order row (deleted, purged): read as written.
- A touched cart still open (`status = 'incomplete'`) and started under 6 hours before now is in no figure; it enters once the 6 hours pass.
- Reached: those carts. Came back (`returned`): a touch clicked or restored. Recovered: a touch with `recovered = 1`. Revenue: its `completed_total` (minor units).
- Untouched: the `channel = ''` rows completed in the period plus open carts (`status = 'incomplete'`, a line by `pg_order_item_active_sql()`, no touch) started in the period and at least 6 hours old. Left = reached + untouched.
- Late cart, architect (2026-11-15, agent channel message 5284): completed more than 7 days after its last touch, it counts as left, reached and came back when clicked or restored, never as recovered, and its total stays out of the revenue.
- By channel (order: email, whatsapp, sms, page) and by message (`source_type:source_id`, `sent` counts every send): a cart counts once each; recovery goes to the taking touch's channel and message.
- Message names: an e-mail campaign's subject, else "E-mail campaign / Message template / Pop-up #id".

## Where is it shown?
- Section `cart_recovery` ("Recovered carts") of the Visitors overview (`includes/visitors/cart_recovery.php`), after Conversions: five tiles with the change against the earlier period, the funnel as bars, the channel table, the message table (most recovered revenue first, 5 rows then Show all), the rule line. Drawn only with commerce on and the table there; the Visitors menu anchor follows the same check.
- Owner (2026-11-15, poll 55): the look as drawn, and the section between Conversions and Sources (`workflow/decisions-made-product-57.md`). The Left carts tile shows a rise as worse.
- Dashboard card `recovery` (`includes/dashboard/v2/cards/recovery.php`, renderer in `pg_dash_cards_shop.js`): gate `shop_summary`, default Last 30 days, revenue as the headline with the change, a row per channel, See all to `visitors.php#cart_recovery` for those who may open the Visitors overview.
- `GET /reports/cart-recovery` (scope `orders:read`, `api_reports_cart_recovery()` in `includes/api/resources/reports.php`, type `CartRecoveryReport`): whole site days, 30 by default, at most 400; 503 before the table exists.
- Until #10089 the handler returned its answer instead of sending it with `api_ok()`, so every call answered 500 `server_error`. `tests/api_handler_sends_test.php` now fails any core handler in `includes/api/resources/` that returns a value.
