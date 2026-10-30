---
name: shipment-label-pickup
description: Carrier pickup requests (2026.11.0, 11.0.131, #3415): pickup_ref/pickup_at, the driver pickup answer, list and label card dialog, groups, window, double-booking warning
module: shipment_labels
---

## What is kept?
- `shipment_labels.pickup_ref` VARCHAR(64) (the carrier's confirmation) and `pickup_at` (the start of the window asked for, unix time), step `upgrade_2026_11_0_shipment_label_pickup()` (11.0.131, `install_add_column()` only; decided in `workflow/decisions-made-schema-5.md`). Every label one request covered gets the same pair; '' and 0 mean none. Readers ask `pg_shipment_labels_pickup_ready()` (exact-name `pg_schema_has()`).

## What does a driver answer?
- The contract's `pg_label_<key>_pickup($request, $account)` (`carrier-label-drivers.md`): `$request` = `references` (the labels' service references), `start`, `end` (unix). Success = `reference` is the confirmation (at most 64 characters, else the caller treats it as unclear). A driver without `pickup` is not offered (`pg_shipment_label_pickup_drivers()`: `pg_label_can()` + `pg_label_ready()`).
- Shippo, Sendcloud, and the drivers without a pickup: `shipment-label-pickup-drivers.md`.

## Where is it asked?
- Orders list: "Request Pickup" in the selected-orders bar (only while a pickup driver is on) opens `<dialog id="shipment_pickup_dialog">` inside the list form; it posts `label_request=bulk_pickup` with `orders[]`.
- Order screen label card: "Request Pickup" per address with an active outbound label of a pickup driver; its dialog's fields belong to `shipment-label-form` (`form=` attribute) and post `pickup-<ship_to_id>`. Each label shows "Pickup booked: ref, date time".
- Both dialogs come from `pg_shipment_label_pickup_dialog()`: date (tomorrow), From/To (09:00-17:00), "Book another pickup", the warning when a label of the address is booked.

## How is a request run?
- `shipment_label.php` → `pg_shipment_label_pickup_rows()` (active outbound labels, the parcel's `carrier` joined by (ship_to_id, number)) → `pg_shipment_label_pickup_request()`: `pg_shipment_label_pickup_groups()` (one request per driver + carrier, cancelled, returns and other drivers left out), `pg_shipment_label_pickup_window()` (site time zone; bad date/time, end before start, a window gone by refused).
- Double booking: without `pickup_again` any booked label (`pg_shipment_label_pickup_booked()`) stops the whole request with `pg_shipment_label_pickup_warning()` naming the confirmations; nothing is sent.
- Only a success writes `pickup_ref`/`pickup_at` on the group's ids, with `log_activity()`. Notices go back to the list or the order.

## What is not done yet?
- The shipments API fields `pickup_ref`/`pickup_at` (after #3298).
- Tests: `tests/shipment_label_pickup_test.php` (step source, probe, groups, warning, window, dialog); the drivers' in `shipment-label-pickup-drivers.md`.
