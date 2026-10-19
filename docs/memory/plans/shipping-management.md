---
name: shipping-management
description: Shipping plan (2026-10-08; phase 1 done but new carriers): shipment row status, single write path, panel, customer e-mail and guest tracking, bulk CSV, API fields, more carriers
---

## What state is it in?
- Phase 1 built (#601); no `includes/fn/shipping.php` yet. Its labels 8.20–8.22 (2026.4.8) are void: take free labels of the open version from its `upgrade_to_…()` entry function. Full old text (stale line numbers): `git show 41aea113:docs/_plan_kargo.md`.
- Every phase is one PR; `tests/shipping_test.php` pins today's behaviour and changes only together with a test (red first).

## Which decisions are fixed?
- `orders.tracking_code` is a campaign code, unrelated to shipping. `orders.tracking_company` and `orders.notes` are reserved for ERP: do not write them; the carrier goes on the shipment row.
- `_order_has_shipped()` stays as is: any non-empty tracking number = shipped (cancel gate, docs/memory/commerce/order-cancel-shipping-gate.md). A format warning never changes that.
- ShipWorks path and the `get_express_order.php` / `get_order_preview.php` totals stay unchanged.
- Number format checks only warn, never block. Carrier label/create APIs are not in this plan; `status` and `source='carrier'` are ready for them.

## What are the phases?
1. Carrier recognition: method code, site default, number shape and Turkish capitals done in #601 (`commerce/order-view-tracking-link.md`); the helpers read the default themselves, so no caller passes it. Left: hepsijet, trendyolexpress, kolaygelsin, horoz, ceva, sendeo, each once its tracking URL is checked on its own site (candidates in that file), then n11 `HLZ`/`CEVA`/`BL` aliases.
2. Schema: `shipping_tracking_numbers` adds `carrier`, `tracking_url`, `status` (VARCHAR, values in code: created, in_transit, out_for_delivery, delivered, returned, exception), `status_at`, `shipped_at`, `delivered_at`, `created_at`, `source` (default `panel`), `format_warning`; drop duplicate (`ship_to_id`, `number`) rows except the lowest id, then a unique key. `shipping_methods.carrier`. Backfill `shipped_at` from `ship_tos.ship_date` (compare with `'0000-00-00'`). Add `order_delivered` to `email_campaign_profiles.action` (read the ENUM first).
3. One write path (M): `pg_shipment_record($order_id, $ship_to_id, $number, $opts)` UPSERTs (no delete + insert) and sets the format warning; `pg_shipment_delivered($ship_to_id, $time)` fires `order.delivered` and the campaign exactly once. `update_order.php`, `mp_order_apply_tracking()` and `mp_order_mark_dates()` use it.
4. Panel (M): carrier select per number (+ Automatic), status badge, yellow format warning; tag input ids `ship_to_id_tracking_numbers_<id>` so two recipients work.
5. Customer side (M): mail-merge fields `^^tracking_numbers^^`, `^^carrier_name^^`, `^^tracking_url^^`; `order_delivered` trigger; widget `__tracking_*` tokens read the row first; guest tracking by order number + billing e-mail (CSRF, 10/hour/IP, shipping data only). Leave the TR seed page "Kargo Yola Çıktı"; tell operators via `install_note()`.
6. Bulk (S–M): "mark shipped" on selected orders and a CSV upload (`order_number,number,carrier`, `fgetcsv`) with a result list; role gate `USER_MANAGE_ECOMMERCE`.
7. API (S–M): validate `carrier` in `api_orders_ship()` (422); carrier name, URL, status and dates in shipments and the `order.shipped` / `order.delivered` payloads. n11 import: city = district, state = province for new imports only.
8. Optional: `ship_tos.district`, `states.plate_code`, postcode-province check in warning mode; no district data set (licence decision).

## What must the owner check by hand?
- Live carrier tracking URLs, n11 province/district on a live order, the campaign e-mail via real SMTP, `^^tracking_url^^` on existing sites.
- Questions to raise in the PRs: `orders.tracking_company`, ShipWorks, old n11 records, unread `ERP_AUTO_INVOICE_ON`.
