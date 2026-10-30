---
name: carrier-webhook-basitkargo
description: Basit Kargo's side of the carrier webhook (#3645): added by hand in its panel, two unsigned messages (status change, movement), latest trace by time
module: carrier_webhook
---

## How does the merchant set it up?
- Basit Kargo has no registration API. `pg_label_basitkargo_webhook_manual()` marks the driver as by hand (`carrier-webhook.md`): a save with the driver on and ready makes the key, the card shows the address with the panel path `Yönetim Paneli → Ayarlar → Webhook` and the two events to tick, `Durum Değişikliği` and `Kargo Hareketi` (Basit Kargo's own panel words, not translated). Switched off, the key is dropped and the old address answers 403; the address in Basit Kargo's panel stays until the merchant removes it.

## Which messages does it read?
- Reference: basitkargo.com/api, section Webhook. Neither message is signed and no header is documented, so the key in the URL is the credential (as with Geliver and Shippo).
- Status change: `{id, orderNumber, orderId, barcode, status, handler {name, code}, handlerShipmentCode}`, no time. `status_at` is 0, so `pg_shipment_tracking_decide()` dates it at arrival (never stale).
- Movement: the GET /v2/order/{id} body, traces included; `type` INCOMING gives `is_return` (the label row decides as well).
- `pg_label_basitkargo_webhook_read()`: no JSON object, or no `id` / `status` → false (400); an `id` that is not a Basit Kargo id → no event; the order `id` is the label reference. A status change's top-level handler is moved into `shipmentInfo`, then both shapes go through `pg_label_basitkargo_track_answer()`, shared with track(): `pg_label_basitkargo_status()`, number (`handlerShipmentCode`, else `barcode`), carrier. An unknown status is a failed answer: nothing is written.

## Which trace dates the status?
- The reference example lists traces newest first; track() took the last one as the newest. `pg_label_basitkargo_latest_trace()` now takes the trace with the latest `time` (unreadable time never wins; equal times: the first listed), for track() and the movement message alike.

## What is unverified?
- No test mode (`workflow/decisions-made-product-3.md`): fixtures only (`tests/fixtures/basitkargo/webhook_status.json`, `webhook_movement.json`), shaped from the reference. Unknown without a live call: retries, timeouts, whether a status change for a return carries `type`, and the real sort order of traces.

## How is it tested?
- `tests/carrier_webhook_basitkargo_test.php` (both shapes, unreadable bodies, newest-first movement, by-hand marker); `tests/carrier_webhook_test.php` (by-hand key decision, card row); `tests/carrier_label_basitkargo_test.php` (trace order in track()).
