---
name: visitor-push-stock
description: Back-in-stock notice to a subscribed browser (VP5, #9975): push_secret on the request, a site_push_watch row (kind 2), the push beside the mail, once, the forget hooks
module: stock_notify
---

## How does a request name a device?
- `stock_notify.php` takes an optional `push_secret` (the secret the browser got from `site_push.php`). `pg_stock_notify_request()` passes it, with the row's id, to `pg_stock_notify_push_watch()` when the status is `added` or `already`: the browser waits in `site_push_watch` (kind 2 `PG_SITE_PUSH_KIND_STOCK`, `record_id` = `stock_notify.id`; `visitor-push-watch.md`). Pressing on a second device adds that device to the same row.
- An unknown secret, or visitor notifications off, is ignored: no error, the request stands. The e-mail address stays required.
- The page's side (the site script putting the secret into the request form) waits for VP3's script, like VP6b's button.

## How is the notice sent?
- `pg_stock_notify_send()`, after it claims a row, calls `pg_stock_notify_push_send()` while `pg_site_push_enabled()` and the 11.0.229 table exist: per waiting browser (`pg_site_push_watchers()`, only subscriptions that still exist) the wait is taken with a DELETE (stock's only moment, `pg_site_push_watch_take(…, 0, true)`); one deleted row writes one message (`pg_site_push_message_to_one()`, kind 2). Two restocks at once push once.
- Beside the mail or text, never instead (2026-11-16, given with the site_push_watch answer): a push unseen for a day is dropped, so it cannot carry the one-message promise.
- The text: `pg_stock_notify_push_input()`: the product's name as the title (one line, cut to 60), "Back in stock. You asked to be told once." in the subscription's language, the product's path (`pg_site_push_url_path()`), `/` without one.

## What removes the waits?
- `pg_stock_notify_forget()` (account deleted, product deleted for good) runs `pg_stock_notify_forget_watch_sql()` before the requests go, so a reused id never inherits a device.
- A row sent with the switch off, or the trim's year-old requests: the sender's tidy-up drops waits after 180 days or once the subscription is gone.

## How was it verified?
- `tests/stock_notify_test.php`: the text, the forget statement, the wiring (request, sender order, endpoint). No sandbox run: the table, take and writer are VP6's, pinned by `tests/site_push_test.php`.
