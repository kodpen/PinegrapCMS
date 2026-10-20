---
name: shipment-write-path
description: The single shipment write path (includes/fn/shipping.php): pg_shipment_record() upsert, pg_shipment_prune(), pg_shipment_delivered() once per address, the format warning, who calls it
---

## What writes shipping_tracking_numbers?
- Only `includes/fn/shipping.php` (2026.11.0, PR #879): `pg_shipment_record($order_id, $ship_to_id, $number, $opts)` adds a row or updates the one with the same (`ship_to_id`, `number`) in place (never delete + insert, so id, source and dates survive); `pg_shipment_prune($ship_to_id, $numbers)` removes the address's rows not in the list (the list-replace of the order screen and the API); `pg_shipment_delivered($ship_to_id, $time, $opts)` marks an address delivered.
- Callers: `update_order()` (order screen, panel AJAX, Workspace: source `panel`; `api_orders_ship()` passes `source => api`), `mp_order_apply_tracking()` / `mp_order_mark_dates()` (source `marketplace`). `shipworks.php` still inserts directly (unchanged; owner question). `delete_order.php` deletes an order's rows.
- Before step 11.0.20 has run (`pg_shipment_rows_ready()`: all nine columns plus `shipping_methods.carrier`) the record writes the number alone and the delivery has only the caller's guard.

## What does a row get?
- New row: `status` created, `status_at`/`created_at` now, `shipped_at` now (or `$opts['shipped_at']`), `source`, `tracking_url` only when an absolute http(s) address (`pg_shipment_tracking_url_clean()`: it is printed as a link).
- `carrier` only when somebody named it: `$opts['carrier']` (key or name, read by `pg_shipping_method_carrier()`), else the row's own, else `shipping_methods.carrier`, else a carrier the method code spells. The site default and the number's shape are NOT stored, so fixing the default still fixes old parcels at read time.
- An old row with `shipped_at` 0 takes the address's `ship_date` (noon) once that day has come.
- `format_warning` is recomputed on every write against the carrier the customer is shown (named, else `pg_shipping_carrier()` with the default).

## What does the format warning check?
- `pg_shipment_number_warning()`: every number 5–40 of `[A-Za-z0-9 -]`, starting alphanumeric; yurtici, surat, aras, mng 8–20 digits; ptt 8–20 digits or a valid UPU S10 (`pg_shipment_s10_valid()`, mod 11); UPS/FedEx/USPS one of their patterns; other or unknown carriers only the general shape. A warning never blocks and `_order_has_shipped()` ignores it. Shapes unverified on live numbers.

## When is a delivery recorded?
- `update_order()`: `pg_shipment_delivery_entered($before, $after, $today)`, i.e. the save enters or changes the delivery date to a day that is not after today. The estimate sent back unchanged records nothing; a future date is an estimate. The old rule ("the date was empty before") fired almost never on web orders, and fired on future dates typed into an empty field.
- `pg_shipment_delivered()` stamps `status` delivered, `delivered_at` on the address's numbered rows with `delivered_at` 0; it announces `order.delivered` (`pg_announce`, payload unchanged) and starts `order_delivered` campaigns only when the stamp changed rows and none had arrived before. Resaves, corrections, re-sent polls and racing writers give one event; a parcel added later is stamped silently; the first arrival time is never moved; clearing the date does not undo a delivery.
- An address with no numbered row uses `$opts['first']` (the caller's own transition), so there a correction past → past day announces again, and a number added after such a delivery can announce a second time.
- `notify` false (API `notify` false, marketplace) skips the customer e-mail, not the event. The marketplace import calls it per address on every delivered poll; `first` is its "delivery_date was empty" update.

## What is still open?
- Phase 4 (carrier select per number), 5 (mail-merge fields, readers of the row), 7 (API fields, carrier validation) in `plans/shipping-management.md`; `shipping-carrier-labels.md` writes through this path.
