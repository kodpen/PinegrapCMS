---
name: shipment-write-path
description: The single shipment write path (includes/fn/shipping.php): pg_shipment_record() upsert, pg_shipment_prune(), pg_shipment_delivered() once per address, the format warning, who calls it
---

## What writes shipping_tracking_numbers?
- Only `includes/fn/shipping.php` (#879): `pg_shipment_record($order_id, $ship_to_id, $number, $opts)` adds a row or updates the one with the same (`ship_to_id`, `number`) in place (id, source and dates survive); `pg_shipment_prune($ship_to_id, $numbers)` removes the address's rows not in the list; `pg_shipment_delivered($ship_to_id, $time, $opts)` marks an address delivered.
- Callers: `update_order()` (order screen, panel AJAX, Workspace: source `panel`; `api_orders_ship()`: `api`), `mp_order_apply_tracking()` / `mp_order_mark_dates()` (`marketplace`). `shipworks.php` inserts directly (owner question). `delete_order.php` deletes an order's rows.
- Before step 11.0.20 (`pg_shipment_rows_ready()`) the record writes the number alone and the delivery has only the caller's guard.

## When is the list replaced?
- `update_order()` replaces it only when the address's request has the `tracking_numbers` key. `api_orders_ship()` sets the key only when the caller sent the field (`api_orders_ship_recipient()`, #1232, issue #976). A date-only call used to send `[]`: every row was pruned, the order stopped counting as shipped and each date correction announced `order.delivered` again. `[]` sent on purpose still removes them.

## What does a row get?
- New row: `status` created, `status_at`/`created_at` now, `shipped_at` now (or `$opts['shipped_at']`), `source`, `tracking_url` only when an absolute http(s) address (`pg_shipment_tracking_url_clean()`).
- `carrier` only when named: `$opts['carrier']` (key or name, `pg_shipping_method_carrier()`), else the row's own (skipped with `carrier_chosen`, the screen's Automatic), else `shipping_methods.carrier`, else a carrier the method code spells. Site default and number shape are not stored: a fixed default fixes old parcels at read time.
- An old row with `shipped_at` 0 takes the address's `ship_date` (noon) once that day has come.
- `format_warning` is recomputed on every write against the carrier the customer is shown (named, else `pg_shipping_carrier()` with the default).

## What does the format warning check?
- `pg_shipment_number_warning()`: every number 5–40 of `[A-Za-z0-9 -]`, starting alphanumeric; yurtici, surat, aras, mng 8–20 digits; ptt 8–20 digits or a valid UPU S10 (`pg_shipment_s10_valid()`, mod 11); UPS/FedEx/USPS one of their patterns; others only the general shape. A warning never blocks and `_order_has_shipped()` ignores it. Shapes unverified on live numbers.

## When is a delivery recorded?
- `update_order()`: `pg_shipment_delivery_entered($before, $after, $today)`, i.e. the save enters or changes the delivery date to a day that is not after today. The estimate sent back unchanged records nothing; a future date is an estimate.
- `pg_shipment_delivered()` stamps `status` delivered, `delivered_at` on the address's numbered rows with `delivered_at` 0; it announces `order.delivered` (`pg_announce`) and starts `order_delivered` campaigns only when the stamp changed rows and none had arrived before. Resaves, corrections, re-sent polls and racing writers give one event; a parcel added later is stamped silently; the first arrival time is never moved; clearing the date does not undo a delivery.
- An address with no numbered row uses `$opts['first']` (the caller's own transition), so there a correction past → past day announces again, and a number added after such a delivery can announce a second time.
- `notify` false (API `notify` false, marketplace) skips the customer e-mail and SMS, not the event. The marketplace import calls it per address on every delivered poll; `first` is its "delivery_date was empty" update.

## What is still open?
- Phases 5 (mail-merge fields, readers of the row) and 7 (API fields) in `plans/shipping-management.md`; the order screen: `order-screen-shipments.md`.
