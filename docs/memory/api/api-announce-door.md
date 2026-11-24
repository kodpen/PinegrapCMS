---
name: api-announce-door
description: The one door for announced moments: pg_announce() is the only caller of api_webhook_enqueue(); the listeners option keeps the quiet callers as they were; erp_event() goes through it
module: events
---

## Which function announces a moment?
- `pg_announce($event, $data, $options)` (`includes/fn/events.php`) is the only product code that calls `api_webhook_enqueue()` (since #11479). A new direct call fails `tests/announce_door_test.php`.
- Order inside: `pg_event_record()` (Workspace), then `pg_announce_listeners()` unless the caller turned them off, then `api_webhook_enqueue($event, $data, true)`. It returns the rows queued (one per subscription), 0 when the API is missing.
- Why: the add-on hooks (AH5 of `plans/addon-hook-system-steps.md`) sit in this one function, so add-ons and webhooks see the same moments.

## What does `array('listeners' => false)` do?
- Records and queues the event without the in-process listeners: page cache dirty, back-in-stock (`pg_stock_notify_heard()`), ActivityPub, contact-group mail, feedback sends, Slack/Teams/Telegram (`pg_notify_announced()`).
- The callers that queued directly before #11479 pass it, so nothing changed for them: the API resources (products, product groups, pages, files, customers, orders, inventory), `order.created` (`pg_order_complete()`), `order.cancelled` (`process_order_cancellation()`), `order.shipped` (`update_order.php`, `shipworks.php`, marketplace import), `product.deleted` (`product_builder.php`), `form.submitted` (`custom_form.php`) and `erp_event()`.
- Several of them call a listener themselves (the stock API calls `pg_stock_notify_heard()`, `custom_form.php` calls `pg_feedback_heard()`); turning the listeners on for one of them would run it twice. Opening a quiet caller to the listeners is a change of its own, checked listener by listener.

## What does `erp_event()` do now?
- The ERP audit trail and alerts first (`erp_audit_event()`, `erp_alert_event()`), then `pg_announce(..., array('listeners' => false))`; its return is the rows queued, as before.
