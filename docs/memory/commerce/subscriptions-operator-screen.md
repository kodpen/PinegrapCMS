---
name: subscriptions-operator-screen
description: Operator Subscriptions screen (#3343): view_subscriptions.php list and filters, ?id= row page, pause/resume/cancel at period end through the shared move, operator named in the event
module: subscriptions_operator
---

## Where is it and who opens it?
- `view_subscriptions.php`, gated like `view_orders.php` (`validate_user()` + `validate_ecommerce_access()`), in the Orders menu row (10) after Cart quotes; the row lights for it (`output_menu()`). Module `includes/fn/subscriptions_operator.php`.
- The list: subscription, customer (billing name and e-mail of the first order, else the account's username), product, amount + currency, period, status, next charge (only for active/past_due/paused), card last 4. Next charge first, rows with none last, at most 500 (a note says so).
- Filters (GET, one form in the toolbar): status, next-charge range (whole days, `next_to` inclusive), search by customer: billing first/last/full name, billing e-mail, account username and e-mail (`pg_subscription_operator_filter()`, `pg_subscription_operator_where()`, LIKE-escaped).

## What does the row page show?
- `?id=`: the facts (customer, product, amount, period, status with failed attempts, next charge or "Runs until" for a cancelled row in a paid period, card, driver, first order link, dates), renewal orders (orders named by its events other than the first order, linked to `view_order.php`) and the history (every event, newest first, with order, amount and detail). Plain tables: their order is the meaning.

## Which moves may staff make?
- Pause (active), resume (paused), cancel at period end (active, past_due, paused): `pg_subscription_operator_actions()` = the customer's actions without `update_card` (only the customer opens the PSP's card page). Never "cancel now", end, refund, price or period edits; refunds stay on the renewal order's screen (#3343).
- POST with the form token to the row page; `pg_subscription_operator_action($user, $id, $action)` checks with `pg_subscription_operator_refusal()` (the words of a refused move: past_due cannot pause, already paused, only a paused row resumes, cancelled/ended rows cannot change, other actions are not possible) and then `pg_subscription_store_move()`.
- `pg_subscription_store_move()` (in `subscriptions_account.php`) is the one move both paths share: `pg_subscription_set_status()` (conditional on the status read, event `status_<to>`, the cancel mail), and on cancel the stored card forgotten unless another running line of the order uses it. A move lost to a concurrent one is refused with "changed meanwhile".
- The event's `detail` names the operator: `operator: <username or e-mail> (#<user id>)`, cut to 255 (`pg_subscription_operator_detail()`); the customer's own moves write `customer`.

## Tested?
- `tests/subscriptions_operator_test.php`: actions by status, every refused move with a reason and nothing written, each allowed move's event with the operator, the shared card, no end/cancel-now path, the gate and token in the screen, the menu entry, filters and WHERE. Not run on a database or in a browser.

Related: commerce/subscriptions-account.md, commerce/subscriptions.md, commerce/subscriptions-mails.md, plans/subscriptions-iyzico-stripe.md
