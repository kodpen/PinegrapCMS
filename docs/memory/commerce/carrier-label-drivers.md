---
name: carrier-label-drivers
description: Carrier label driver layer (2026.11.0): pg_label_<key>_ contract, pg_label_call() answers, what reaches the shipment row, connect_apps label_<key>, Shipping card and test
module: carrier_labels
---

## What is a label driver?
- A file `includes/shipping/<key>.php` listed in `pg_label_builtin_drivers()` (Geliver: `carrier-label-geliver.md`, Basit Kargo: `carrier-label-basitkargo.md`) or an add-on file registered with `pg_label_driver_register()` (absolute path, a taken key refused). Contract, registry, answers, settings: `includes/fn/carrier_labels.php`. Function tables, not an interface, as `payment-drivers.md`.
- Required `_info()`: `carriers` (kept only when a `pg_shipping_carriers()` key) and `formats` (`pdf`, `zpl`, `svg`). Optional `_fields()` (the payment field shape, `pg_payment_fields_normalize()`, switches dropped) and operations `test`, `create`, `label`, `cancel`, `track`, `pickup` (`pg_label_operations()`); `pg_label_can()` asks first.
- The account is the last argument of every operation (`pg_label_account()`: stored values, secrets unsealed, defaults). A driver never reads the database, request or session and never writes orders or parcels.

## What does a caller get back?
- Always `pg_label_call($key, $op, $args)`: pads/cuts args, catches `Throwable` (an `\Error` is a plain failure, an exception `unclear`), then masks every secret value of the account (4+ characters) as `****` in `error`, `log` and `raw`, so a service echoing the request cannot put a key into a notice or the log.
- `pg_label_answer()` keeps only its keys (reference, tracking_number, carrier, tracking_url, status, status_at, label, label_format, barcode, account, log, raw, unclear): a service's own fields (fee, COD amount) are dropped there.
- A success becomes an unclear failure when create() has no `reference` or no tracking number of a parcel's shape (5–40 of `[A-Za-z0-9 -]`), label() has no document of its format (PDF starts `%PDF-`, ZPL holds `^XA`, SVG passes `pg_label_svg_valid()`), track() no `pg_shipment_statuses()` value.

## What reaches the shipment row?
- Only `pg_label_shipment_options($answer)`: `source` carrier, plus `carrier` and `tracking_url` when given, for `pg_shipment_record()` (`shipment-write-path.md`). The service's `reference`, label and barcode never go into `shipping_tracking_numbers`; they are kept in `shipment_labels` (`shipment-labels.md`). This is how the aggregator choice stays out of the schema. `track()` answers reach the row through the tracking job (`shipment-tracking.md`).

## Where are the settings?
- `connect_apps` row `label_<key>` as payment drivers: `client_id` empty, secrets sealed as one map (`pg_connect_seal()`), other fields in `options`, `enabled` = the driver's switch. No schema; several drivers may be on at once. `pg_label_ready()`: on, readable, every required field stored.
- Reset Encryption Key does not re-seal `connect_apps`; the card warns that the stored keys cannot be read.

## What does the Shipping card do?
- `pg_label_settings_rows()` after FedEx in `includes/settings/commerce.php`: nothing while no driver exists; per driver a switch, its fields (secrets never drawn back, a `__present` marker) and "Save and test the connection" (`label_test` = key) when it defines `test`.
- `commerce.save.php`: `pg_label_settings_check()` before the card's UPDATE (marks refuse it), `pg_label_settings_save()` after, which then runs the test with the stored settings and adds "accepted" / "refused the connection" notices, and puts the tracking job in the dispatch list for a service on that can track.
- `pg_label_settings_decide()` (pure): only posted drivers; empty secret keeps; select outside options keeps; required only while on; no row while off with defaults.

## How was it verified?
- `tests/carrier_labels_test.php` with a test driver in the file (contract, answers, masking, row options, decide). The card was not opened on a sandbox; since #3096 it draws the Geliver driver.
