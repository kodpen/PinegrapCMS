---
name: carrier-webhook
description: carrier_webhook.php (2026.11.0, #3245): a label service's parcel status messages written through the tracking write-back, key in the URL, optional signature check, registration, answers, firewall
module: carrier_webhook
---

## What is it?
- `POST carrier_webhook.php?driver=<key>&key=<secret>`, functions in `includes/fn/carrier_webhook.php` (loaded by the entry and the Shipping card). A driver joins with optional `pg_label_<key>_webhook_read($body)`, `_webhook_verify($raw, $headers, $account)`, `_webhook_register($url, $account)`, `_webhook_remove($id, $account)`; Geliver is the first (`carrier-label-geliver.md`), Shippo the second (#3407, `carrier-label-shippo.md`), Sendcloud the third (#3431, `carrier-label-sendcloud.md`), Kargonomi the fourth (#3633, `carrier-webhook-drivers.md`). A driver without register gets no webhook line on the card (no key is made). The hourly poll (`shipment-tracking.md`) stays as the safety net, budgets unchanged.

## Where is the secret?
- No schema: `webhook_key` (32 hex, `random_bytes`) and `webhook_id` (the service's) are extra entries of the driver's sealed secrets in `connect_apps` `label_<key>` (`pg_carrier_webhook_secrets_put()`, null removes). `pg_label_settings_store()` merges secrets, so a save keeps them; they are never account fields.

## When is it registered or removed?
- `pg_label_settings_save()` calls `pg_carrier_webhook_sync()` per posted driver: on and ready without an id (or with newly typed secrets) → register (old id removed first), store key and id, notice; a refusal keeps the key and the card shows the URL to add by hand (masked in notices). Switched off → remove at the service, drop id and key, so the old URL answers 403.

## What does the receiver answer?
- 405 not POST; 404 driver unknown or without `webhook_read`; 429 over 300 a minute per address (`waf_rate`, `rate-carrier-webhook` logged); 503 labels/rows schema missing; 403 e-commerce off, driver off or no key; 401 wrong key (`hash_equals`, a firewall offence like marketplace_webhook.php); 413 over 1 MiB; 401 `wrong_signature` when the driver's verify refuses the raw body (`pg_carrier_webhook_verified()`, headers by lower-case name from `pg_carrier_webhook_headers()`; same offence; no verify = true); 400 unreadable; else 200 `{ok, applied, ignored, unknown}`.

## What does a message change?
- `pg_carrier_webhook_decide()` (pure) over `pg_carrier_webhook_label()` (label by driver + reference, its parcel row LEFT JOINed): unknown → logged once (last 100 in `data/temp/carrier_webhook.json`), 200; return label or the service's return flag → nothing; label not active, row gone, order incomplete/cancelled → nothing; parcel arrived or returned → nothing (a repeated delivery gives no second `order.delivered`); a status older than the row's `status_at` → nothing (out of order; an arrival is never too old); else `pg_shipment_tracking_decide()` then `pg_shipment_tracking_apply()`, the only write.

## How do Shippo, Sendcloud and Kargonomi take part?
- `carrier-webhook-drivers.md`.

## Firewall?
- In `waf_is_api_request()` and `waf_country_api_scripts()` like `payment_callback.php`: no bot policy or country gate, global limit applies.

## How is it tested?
- `tests/carrier_webhook_test.php`: the reference's message, shapes, key check, decision, delivered once, return label, unknown note, register/remove through the stand-in transport, write-path pins; Shippo's side in `tests/carrier_webhook_shippo_test.php`, Sendcloud's (signature included) in `tests/carrier_webhook_sendcloud_test.php`. No live delivery.
