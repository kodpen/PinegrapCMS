---
name: subscriptions-account
description: The customer's subscriptions in My Account (slice 5a): the my_account "subscriptions" section, pause/resume/cancel at period end/update card, ownership, events, the job ending cancelled rows
module: subscriptions_account
---

## Where does the customer see them?
- The my_account system widget's section binding `subscriptions` (designer: "Subscriptions (shop sites; hidden when there are none)"), filled in `_pg_my_account_extras()` by `pg_subscription_customer_section_html(pg_subscription_customer_rows(USER_ID), …)`: product (the first order line's name), amount + currency, status, next charge date (cancelled: "Runs until"), card last 4, the last event. Site output, Bootstrap 5.3.8. Not in any starter or design template yet: a designer adds the section; the legacy `get_my_account.php` page shows nothing.
- A past_due row whose latest renewal event is `confirm_needed` shows its https link ("Confirm the payment", `pg_subscription_customer_confirm_url()`).

## Which actions, from which status?
- `pg_subscription_customer_actions()`: active → pause, cancel, update_card; past_due → cancel, update_card (no pause: it would stop the 1/3/7 retries of an unpaid period); paused → resume, cancel, update_card; cancelled/ended → none. A move is offered only when `pg_subscription_transition_allowed()` allows it.
- Each form posts to `subscription_action.php` (POST only, form token, signed in) with `id`, `action`, `send_to` (the page's path, `pg_safe_redirect_path()`). Notices go to the `my_account` liveform.
- `pg_subscription_customer_action($user_id, $id, $action, $return_url, $io)`: the row read by id is refused as `not_found` unless its `user_id` is the customer (no hint the id exists); moves go through `pg_subscription_set_status($id, $to, 'customer')`, which writes event `status_<to>` (and the cancel mail). A move lost to a concurrent one answers `refused`.
- update_card: the driver's `update_card()` with `return_url` = the page; only an https answer redirects (303), with event `card_update_opened`.

## How does cancel at period end work? (#3183: no proration)
- Cancel moves the row to `cancelled` at once and keeps `next_charge_at`; a cancelled row is never due (`pg_subscription_renewal_due_sql()`), so nothing is charged again. The stored card is forgotten (`subscription_cancel`) unless another active/past_due/paused row of the same order and driver uses it; a refusal there is logged and the cancel stands.
- `recurring_payment_job.php` runs `pg_subscription_cancelled_ends_run()` after the renewals: rows with `pg_subscription_cancelled_is_over()` (next_charge_at reached or 0, or cancelled while past_due: `retry_count` > 0) move to `ended`; `status_ended` after a cancel mails nothing.
- Membership days and folder access were bought per paid order, so they run out on their own.

## Tested?
- `tests/subscriptions_account_test.php` (stand-in io: ownership, each allowed/refused move, events, shared card, card page, period end, section forms). Not run on a database or against a live service.

Related: commerce/subscriptions.md, commerce/subscriptions-renewal.md, commerce/subscriptions-stripe.md, plans/subscriptions-iyzico-stripe.md
