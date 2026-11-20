---
name: shipment-label-auto
description: Automatic carrier label once an order is paid (2026.11.0, #8432): per-driver switch, the general job's run, its limits, the failed row on the order screen and in the API
module: shipment_label_auto
---

## Why is a label made automatically?
- Owner, 2026-11-09 (`workflow/decisions-made-product-39.md`): a method linked to a label service charges its live price (`shipping-label-checkout-rate.md`), and its label is made once the order is paid, never for an unpaid one (a label costs money). Before, nothing reached Geliver until Create Label was pressed.

## Where is it switched on?
- On the Shipping card, inside each driver that can `create`: "Create the label automatically once the order is paid", off by default. Kept in the driver's `connect_apps` `options` beside its fields: `auto_label` '1' and `auto_label_since` (unix time it was turned on). No schema.
- `pg_label_settings_decide()` (pure, `$now` for tests): on only while the driver is on; the first `since` stays while both stay on; turned off (or the driver off) drops both keys, so turning it on again starts a new `since`. `pg_label_account()` reads fields only, so a driver never sees these values.

## What does the run take?
- `pg_shipment_label_auto_run()` from `job.php` each tick while ECOMMERCE, never in checkout.
- Drivers: `pg_shipment_label_auto_drivers()` = `pg_shipment_label_drivers()` (on, ready, can create) with `pg_shipment_label_auto_since()` > 0.
- Addresses (`pg_shipment_label_auto_candidates()`, oldest payment first; `pg_shipment_label_auto_walk()` reads pages of 50 after the last one read, up to 500 a run, as refused marketplace parcels filled the single 50 before #PR): order not incomplete or cancelled, `paid_at` since the earliest switch and within 3 days (`PG_SHIPMENT_LABEL_AUTO_DAYS`), an active line on the address, no outbound `shipment_labels` row (a failed one included) and no `shipping_tracking_numbers` row.
- Per address the panel's own path: `pg_shipment_label_create_check()` gives the driver, `pg_shipment_label_auto_due()` (pure) wants that driver's switch on and `paid_at` ≥ its `since`, then `pg_shipment_label_create($ship_to_id, 0)` under the address lock. At most 10 labels or failures a run (`PG_SHIPMENT_LABEL_AUTO_LIMIT`).
- `order_date` within 60 days bounds the query on its index (`orders.paid_at` has none). `paid_at` is set at checkout (`pg_order_checkout_paid_at()`) or by Mark Paid (`pg_order_mark_paid()`), so a bank transfer gets its label once marked paid.

## What happens when it fails?
- Each address is tried once. A refusal before the service (busy, a marketplace's own label) writes nothing and the address comes back next run. A refusal or unclear answer of the service writes `pg_shipment_label_failed_row()`: state `failed`, reference `failed-<ship_to_id>` (unique with the driver; `INSERT IGNORE`), no number, no file, user 0; plus the Site Log line "The automatic shipping label of order … could not be created".
- An unclear answer to a label made by hand (Create Label, Create Labels, API) leaves the same failed row with that user's id, inside the address lock (`pg_shipment_label_unclear_mark_due()`), so the run never buys it again (before #PR it retried); its badge: "The service did not say whether this label was created".
- Every reader that looks for a label checks `state = 'active'`, so a failed row is never printed, cancelled, picked up or counted; `pg_shipment_label_document()` refuses it. Bulk create and Create Label still offer the address.
- Order screen: a danger badge "Automatic label could not be created" with the driver and date, no Print or Cancel. API: `shipments[].labels[].state` `failed` (`shipment-label-api.md`); its document route answers 409 `label_unavailable`.

## How is it tested?
- `tests/shipment_label_auto_test.php` (since, due, failed row, no document, walk), `shipment_label_create_lock_test.php`, `test_carrier_labels_decide_auto_label`. The run's query and a real service were not run on a dev DB.
