---
name: erp-alerts
description: ERP bell and device notices (4.91): money in, low stock, quotes running out, refused automatic invoice; the actor rule, who gets banners, and what a new notice needs.
---

## Which notices does the ERP send? (`includes/erp/alerts.php`, 2026.4.4 step 4.91)
- `erp_money`: a collection at or above `config.erp_notify_collection_min` (base currency), from `erp_alert_event()` on `erp.receipt.created`.
- `erp_low_stock` and `erp_quote_expiry`: hourly job `erp_stock_alert_job.php` (`erp_alert_low_stock()`, `erp_alert_quote_expiry()`).
- `erp_auto_invoice`: an order that did not get its automatic invoice (erp-auto-invoice.md).
- Switches on Settings › Commerce › ERP: collections and low stock off by default, the quote notice on (8.58, days ahead 1-30).
- Low stock is a job, not a hook: stock drops in too many places (shop, counter, marketplace, API). A product is told once per drop (`erp_stock_minimums.notified_at`, cleared when it is back above its minimum). A quote is told once (`erp_quotes.expiry_notified_at`, cleared when it is opened again or its date changes).

## How is a notice delivered?
- No channel of its own: `erp_alert_create()` writes the bell row with `create_notification()` and queues device banners with `pg_push_enqueue_notification()` (the overdue reminders use the same pair).
- The person who caused a collection notice is not told: a `notification_reads` row marks it read for them and the push sender skips their devices. Who that is comes from `erp_alert_current_actor()`, never from `USER_ID`/`USER_USERNAME`: through the API or the MCP server those are empty and the application's owner stands in (`test_erp_alert_rows_carry_the_actor`, `tests/erp_api_test.php`). Low stock, quote and automatic-invoice notices go to every user who may see them, the actor included.
- Banners reach only users with a device in `push_subscriptions` (they allowed notifications in the panel). A collection you record yourself never reaches your own phone: test with a second user, or with the low stock job, which has no actor.

## What does a new notice need?
- Its `action` gets a visibility branch in `pg_notification_visible()` and its sentence in `pg_notification_display()` (`includes/notifications.php`); the bell and the service worker read only from there (erp-overdue-reminders.md).
- Visibility today: `manage_erp` with the module on; `erp_money` also needs the cash right.
