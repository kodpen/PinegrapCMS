---
name: shipment-write-path
description: The single shipment write path (includes/fn/shipping.php): pg_shipment_record() upsert, pg_shipment_prune(), pg_shipment_delivered() once per address, the format warning, who calls it
---

## What writes shipping_tracking_numbers?
- Only `includes/fn/shipping.php` (#879): `pg_shipment_record()` adds a row or updates the one of the same (`ship_to_id`, `number`) in place (id, source, dates kept); `pg_shipment_prune()` removes the address's rows not in a list; `pg_shipment_delivered()` marks an address delivered.
- Callers: `update_order()` (order screen, Workspace: `panel`; `api_orders_ship()`: `api`, `shipment-api-fields.md`), `mp_order_apply_tracking()` / `mp_order_mark_dates()` (`marketplace`), label create and tracking (`carrier`, `shipment-labels.md`, `shipment-tracking.md`), `shipworks.php` `updateshipment` (`shipworks`, #3443: `order.shipped` if `created`; no mail, SMS, ERP invoice). `delete_order.php` deletes an order's rows.
- Before 11.0.20 (`pg_shipment_rows_ready()`) only the number is written; a delivery has only the caller's guard.

## When is the list replaced?
- `update_order()` replaces it only when the address's request has the `tracking_numbers` key; `api_orders_ship()` sets it only when the caller sent it (`api_orders_ship_recipient()`, #1232). A date-only call once sent `[]`, pruning every row and re-announcing `order.delivered`; `[]` sent on purpose still removes them.

## What does a row get?
- New row: `status` created, `status_at`/`created_at` now, `shipped_at` now (or `$opts['shipped_at']`), `source`, `tracking_url` only an absolute http(s) address (`pg_shipment_tracking_url_clean()`).
- `carrier` only when named: `$opts['carrier']` (key or name; with `carrier_chosen` an unknown name is kept as typed, #2715, others' unknown text dropped), else the row's own (not with `carrier_chosen`: Automatic), else `shipping_methods.carrier`, else one the method code spells. Site default and number shape stay read-time guesses.
- An old row with `shipped_at` 0 takes the address's `ship_date` (noon) once that day comes.
- Later `status`: a carrier service's `$opts['status']` (`pg_shipment_status_reported()`, not delivered) at `status_at`, on change, before arrival; `existing_only` (tracking job) adds no row, so a removed parcel stays removed.
- `format_warning`: recomputed on each write against the carrier the customer is shown (named, else `pg_shipping_carrier()` with the default).

## What does the format warning check?
- `pg_shipment_number_warning()`: 5–40 of `[A-Za-z0-9 -]`, alphanumeric first; yurtici, surat, aras, mng 8–20 digits; ptt 8–20 digits or a valid UPU S10 (`pg_shipment_s10_valid()`, mod 11); UPS/FedEx/USPS their patterns; others the general shape. Never blocks; `_order_has_shipped()` ignores it. Unchecked on live numbers.

## When is a delivery recorded?
- `update_order()`: `pg_shipment_delivery_entered()`, i.e. the save enters or changes the delivery date to a day not after today (an unchanged estimate records nothing; a future date is an estimate). Tracking: a service's delivered answer, at its time.
- `pg_shipment_delivered()` stamps `status` delivered and `delivered_at` on the address's numbered rows not yet arrived; it announces `order.delivered` (`pg_announce`) and starts `order_delivered` campaigns only when it changed rows and none had arrived before. Resaves, corrections, re-polls and racing writers give one event; a later parcel is stamped silently; the first arrival time never moves; clearing the date undoes nothing.
- An address with no numbered row uses `$opts['first']` (the caller's own transition): there a correction past → past day announces again, and a number added after such a delivery can announce twice.
- `notify` false (API `notify` false, marketplace) skips the customer e-mail and SMS, not the event. The marketplace import calls it per address on each delivered poll; `first` is its "delivery_date was empty" update.

## Where else?
- `plans/shipping-management.md`, `shipment-api-fields.md`, `order-screen-shipments.md`.
