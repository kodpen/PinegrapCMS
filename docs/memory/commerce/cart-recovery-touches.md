---
name: cart-recovery-touches
description: Recovered carts record (cart_recovery_touch, PR #9778): the touch row, its writers, the 6-hour left and 7-day recovered rule, the no-touch row, readiness, what was left out
module: cart_recovery
---

## What is a touch?
- One row of `cart_recovery_touch` per message sent or offer shown to a left cart: `order_id`, `channel` (`email`, `whatsapp`, `sms`, `page`), `source_type` + `source_id` (`campaign` = `email_campaigns.id`, `template`, `popup`), unix times `sent_at`, `clicked_at`, `restored_at`, `completed_at`, `completed_total` (minor units), `recovered`. Step `upgrade_2026_11_0_cart_recovery_touch()`, no backfill.
- Owner (2026-11-15, poll 50): a new table; a cart counts as recovered when completed within 7 days of the last touch (`workflow/decisions-made-product-56.md`, `decisions-made-schema-21.md`).

## Who writes it?
- Only `includes/fn/cart_recovery.php`. `pg_cart_recovery_touch($order_id, $channel, $source_type, $source_id)` is the one touch writer; a second call with the same order, channel and source writes nothing. Its names are checked by pure `pg_cart_recovery_touch_args()`.
- The abandoned-order mail: where it actually goes out, `email_campaign_job.php` (claim path) and Send now (`send_email_campaign.php`), not where `job.php` creates the campaign, so a delayed or cancelled campaign is no touch.
- `?r=<reference code>`: `_eo_restore_cart_from_reference_code()` calls `pg_cart_recovery_restored()` when the session's cart changed; the order's last sent touch gets `clicked_at` and `restored_at` (first visit kept).
- WhatsApp single-use keys and a pop-up taken up on a page: `cart-recovery-whatsapp-page.md`.
- `pg_order_complete()` reads `orders.order_date` before its first write (the completion rewrites it) and calls `pg_cart_recovery_completed()` after the abandoned campaigns are cancelled.

## What is the rule?
- Pure `pg_cart_recovery_outcome($order_date, $completed_at, $touches)`, tests in `tests/cart_recovery_test.php`.
- Left: the cart started at least 6 hours (21600 s) before the completion, as the abandoned-order mail reads it.
- The last touch sent before the completion (later row on the same second) takes it when sent at most 7 days (604800 s) before; an older last touch takes nothing, and no earlier touch replaces it.
- Only a left cart is recovered. A cart completed under 6 hours after it was started was never left: `pg_cart_recovery_completed()` deletes its sent touches (pure `pg_cart_recovery_drop_touches()`, PR #<n>), no `completed_at`, no `channel = ''` row. Cart 12:00, pop-up 12:05, order 12:20: Left 0, Reached 0; completed the next day, it was left and the page touch recovered it.
- Architect (2026-11-20, agent channel message 10532): a touch is of a left cart. The deleted rows were in no figure (an open cart under 6 hours is not read), so past periods do not move.
- Every touch of the order gets `completed_at`; only the taking touch gets `recovered = 1` and `completed_total`, so a sum over a period counts an order once.
- A left cart with no touch sent before the completion gets one row with `channel = ''` and the total: counted apart, never recovered. Reads of sent touches filter `channel <> ''`.
- A left cart whose touches are all older than 7 days gets no total and no `channel = ''` row.

## What else holds?
- Every write is behind `pg_cart_recovery_ready()` (`pg_schema_has()`): before the update, orders complete as before.
- A purged or deleted order keeps its touches, read as written.
- `recovered` and `completed_total` are written once; a later cancel or refund is applied when read (`cart-recovery-figures.md`).

## What was left out, and why?
- No rollup table: a row per message, not per visit, so the table stays small; the figures read it by date range.
- No flow start "When a cart is left" (recovery by WhatsApp or SMS through a flow): a later plan (`core/campaign-profile-flow-gaps.md`). Until then flow and order-moment WhatsApp keys write no touch (`cart-recovery-whatsapp-page.md`).
