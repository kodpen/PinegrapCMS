---
name: shipment-row-schema
description: The shipment row since 2026.11.0 (11.0.20): carrier, status, date, source and warning columns on shipping_tracking_numbers, the unique parcel key, shipping_methods.carrier, the order_delivered action
---

## What does a tracking number row hold since 11.0.20?
- `shipping_tracking_numbers` was `id, order_id, ship_to_id, number`; everything else about a parcel was worked out at every read (`order-view-tracking-link.md`). Step `upgrade_2026_11_0_shipments()` (`includes/migrations/2026.11.0.php`, label 11.0.20) adds: `carrier` VARCHAR(20) (a `pg_shipping_carriers()` key, `''` = still recognised from the method code), `tracking_url` VARCHAR(500) (the carrier's own page when its API gives one, `''` = built from the pattern), `status` VARCHAR(20) default `created` (values in code: created, in_transit, out_for_delivery, delivered, returned, exception; not an ENUM, the list grows with carrier APIs), `status_at`, `shipped_at`, `delivered_at`, `created_at` (unix times, 0 = unknown), `source` VARCHAR(16) default `panel` (panel, api, marketplace, carrier, csv), `format_warning` TINYINT(1).
- `shipping_methods.carrier` VARCHAR(20): the carrier a method ships with; `''` keeps today's recognition from the code. Beside the VUK 509 columns `carrier_title` / `carrier_vkn`, which stay the e-document's.
- `email_campaign_profiles.action` gains `order_delivered` (the ENUM is read with `install_column_info()` and widened, never rewritten).
- The plan's old labels 8.20–8.22 were void (2026.4.8 is released); 11.0.20 is a free general label of the open version.

## Why a unique key, and what did it remove?
- `uk_parcel` UNIQUE (`ship_to_id`, `number`). The panel path (`update_order()`), the external API (`api_orders_ship()`) and the marketplace import (`mp_order_apply_tracking()`) each checked for the pair before writing; the table now enforces it. Before the key is added the step deletes rows that repeat a pair, keeping the lowest id (a self-join DELETE, nothing to do on a second run). The collation decides equality, so `ABC123`, `abc123` and `ABC123 ` count as one parcel: exactly the rows the key would have refused, no more. Verified on a sandbox: three such rows became one, a plain repeat and a second empty number went too.
- Rows with an empty `number` are left (readers already skip `TRIM(number) = ''`); at most one per address can remain.

## What is backfilled, and what is not?
- `shipped_at` is filled from `ship_tos.ship_date` for rows with a number, only when that date is not after today, the rule `erp_order_shipment()` uses: `ship_tos.ship_date` is checkout's estimate (`shipping-today.md`), and a future date is not the day a parcel left. Rows whose date is empty or in the future keep 0 for the write path (phase 3 of `plans/shipping-management.md`) to fill. The UPDATE is `WHERE shipped_at = 0`, so a cut-off run continues and a second run changes nothing.
- `delivered_at`, `status_at`, `created_at` are not backfilled (the delivery date is an estimate too; the row's own creation moment is unknown). `status` reads `created` on every old row.

## What writes and reads the new columns?
- Written only by the shipment write path (`shipment-write-path.md`): `pg_shipment_record()` fills carrier, link, source, dates and the warning, `pg_shipment_delivered()` the delivery. Returns read `carrier` and `delivered_at` (`order-returns.md`); screens, e-mails and the API present them through `pg_shipment_view()` (`order-screen-shipments.md`, `shipment-mail-fields.md`, `shipment-api-fields.md`). The step was run on a fresh install, re-run from 2026.4.10 and a third time on the finished schema: 12 statements first, every one "already exists" after.
