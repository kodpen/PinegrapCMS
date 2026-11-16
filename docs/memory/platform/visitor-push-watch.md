---
name: visitor-push-watch
description: Visitor notifications to one device (11.0.229, VP6): the site_push_watch table a browser waits for one record in, its moments sent once, who deletes its rows, the helpers VP5 and VP6 share
module: site_push
---

## Why is there a fourth table?
- "Notify me when this is back" (VP5) and "Notify me on this device" for an order (VP6) need to know which browser waits for which record; the three tables of VP1 have no place for it. Owner (2026-11-16, agent channel poll 94, message 6781): "Evet, eklenebilir": one table for both, columns the architect's.
- Architect (2026-11-16, agent channel message 6785): the account-only way (no table) was refused: a buyer signed out is never reached, every device of the account is told, and a shipped notice repeats.

## What does site_push_watch hold?
- `upgrade_2026_11_0_site_push_watch()` (11.0.229, in `get_tables()`): `kind` (the message kind it ends in: 2 stock, 3 order), `record_id` (kind 2 `stock_notify.id`, kind 3 `ship_tos.id`), `subscription_id`, `moments` (bits of the moments already sent), `created_at`; all NOT NULL DEFAULT 0. PRIMARY KEY (kind, record_id, subscription_id), KEY `idx_subscription`.
- No `order_id`: an order's rows are found by its addresses' ids.

## How is a moment sent once?
- `pg_site_push_watch_take()` over pure `pg_site_push_watch_take_sql()`: a moment before the last runs `UPDATE … SET moments = moments | bit … AND (moments & bit) = 0`; the last moment (an order's "delivered", stock's only one) deletes the row. The call that changes one row sends; the table is no history.
- `pg_site_push_watchers()` joins `site_push_subscription`: a wait whose browser is gone reaches nobody.
- `pg_site_push_message_to_one($text, $kind, $subscription_id)` writes a kind 2 or 3 message with one queue row (`queued_count` 1, `created_by` 0); the sender's pass sends it like any other.

## Who deletes rows?
- `pg_site_push_watch_forget($subscription_id)` wherever a subscription is deleted: Stop (`pg_site_push_subscription_remove()`) and a 404/410 in the sender's pass.
- `pg_site_push_watch_drop($kind, $record_ids)` when the record goes: `delete_order.php` through `pg_site_push_order_drop()`, before the addresses are deleted.
- `pg_site_push_watch_tidy()` in the sender's tidy-up: rows older than 180 days (`PG_SITE_PUSH_WATCH_KEEP`) and rows whose subscription is gone (the privacy erasure deletes subscriptions by account and does not know this table).
- `pg_site_push_watch_add()` writes with INSERT IGNORE (pure `pg_site_push_watch_insert()`): pressing twice keeps the first row and its moments.
