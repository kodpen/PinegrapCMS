---
name: erp-alerts
description: ERP bell and device notices (4.91): money in, low stock, quotes running out (and their Workspace follow-up), refused automatic invoice; the actor rule, who gets banners, what a new notice needs.
---

## Which notices does the ERP send? (`includes/erp/alerts.php`, 2026.4.4 step 4.91)
- `erp_money`: a collection at or above `config.erp_notify_collection_min` (base currency), from `erp_alert_event()` on `erp.receipt.created`.
- `erp_low_stock` and `erp_quote_expiry`: hourly job `erp_stock_alert_job.php` (`erp_alert_low_stock()`, `erp_alert_quote_expiry()`).
- `erp_auto_invoice`: an order that did not get its automatic invoice, a tax number difference included (erp-auto-invoice.md).
- Switches on Settings › Commerce › ERP: collections and low stock off by default, the quote notice on (8.58, days ahead 1-30).
- Sales quote follow-up (#1826, off by default): `define('ERP_QUOTE_EXPIRY_FOLLOWUP', true);` in config.php. Each quote the bell announces opens a Workspace task through `erp_quote_expiry_followups()`; the bell must be on, it supplies the days and the once-stamp. Details below.
- Low stock is a job, not a hook: stock drops in too many places (shop, counter, marketplace, API). A product is told once per drop (`erp_stock_minimums.notified_at`, cleared when it is back above its minimum). A quote is told once (`erp_quotes.expiry_notified_at`, cleared when it is opened again or its date changes).

## How is a notice delivered?
- No channel of its own: `erp_alert_create()` writes the bell row with `create_notification()` and queues device banners with `pg_push_enqueue_notification()` (the overdue reminders use the same pair).
- The person who caused a collection notice is not told: a `notification_reads` row marks it read for them and the push sender skips their devices. Who that is comes from `erp_alert_current_actor()`, never from `USER_ID`/`USER_USERNAME`: through the API or the MCP server those are empty and the application's owner stands in (`test_erp_alert_rows_carry_the_actor`, `tests/erp_api_test.php`). Low stock, quote and automatic-invoice notices go to every user who may see them, the actor included.
- Banners reach only users with a device in `push_subscriptions` (they allowed notifications in the panel). A collection you record yourself never reaches your own phone: test with a second user, or with the low stock job, which has no actor.

## What does a new notice need?
- Its `action` gets a visibility branch in `pg_notification_visible()` and its sentence in `pg_notification_display()` (`includes/notifications.php`); the bell and the service worker read only from there (erp-overdue-reminders.md).
- Visibility today: `manage_erp` with the module on; `erp_money` also needs the cash right.

## How does a sales quote about to run out become a follow-up? (#1826)
- Only with `ERP_QUOTE_EXPIRY_FOLLOWUP` true and the Workspace on. A switch on Settings would need a `config` column (schema), so it is a config.php constant; the owner was asked about a checkbox.
- Opened as the quote's `created_by` (`pg_load_user_row()`, `ws_viewer()`, members only), skipped when nobody is left to open it as. Channel: the first of `ws_customer_channels('erp_account', account)` (about the account or its contact, most recently active first) the maker may post in; none: the maker's own task.
- `erp_quote_followup_input()` (pure): tag `<#erp_quote:id>`, title "Sales quote <number> runs out" (never the account's name), due on `valid_until` at 23:59, reminded that day at `WS_TASK_REMIND_DAY_TIME` (at once when that has passed).
- `ws_followup_task_open(..., true)`: an open follow-up of the quote is handed back, so a moved date announced again opens no second one. The days window is `erp_quote_expiry_window()`, shared with `erp_quote_expiring_count()`.
- Tests: tests/erp_quote_expiry_followup_test.php. Not run against a database.
