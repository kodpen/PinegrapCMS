---
name: shipment-labels
description: Carrier label storage and order actions (2026.11.0, PR #3117): shipment_labels table, data/shipping_labels/ files and retention sweep, Create Label and reprint on the order, erasure and order delete
module: shipment_labels
---

## Where is a label kept?
- `shipment_labels` (step 11.0.125, in `get_tables()`), decided in #3108: `driver`, `reference` (the service's shipment id, unique per driver), `barcode`, the parcel by (`ship_to_id`, `number`), `label_format`, `label_file`, `state` (`pg_shipment_label_states()`: active, cancelled, failed: `shipment-label-auto.md`), `is_return`, `user_id`, `created_at`, `cancelled_at`, `label_removed_at`. The parcel row in `shipping_tracking_numbers` stays provider-neutral (`carrier-label-drivers.md`); a label row outlives it.
- The document is a file in `data/shipping_labels/` (closed to the web and the file manager). Name `<order>_<ship_to>_<driver>_<16 hex of sha1(driver, reference)>.<ext>` (`pg_shipment_label_file_name()`); `pg_shipment_label_path()` gives a path only to a name of that shape.

## Who hands a document out?
- Only `shipment_label.php?id=N`, behind the order screen's gate (`validate_user()` + `validate_ecommerce_access()`): the kept file, else the service's copy by reference (`pg_shipment_label_document()`; a marketplace's label: the marketplace's copy), not written back so the retention clock is not restarted. PDF inline, ZPL a download, SVG a print page; `no-store`, `nosniff`. Never `get_file.php`.

## How is a label created?
- The order screen's shipping card lists the address's labels (Print Label) and draws "Create Label" while a driver is on (`pg_shipment_label_drivers()`: `create` + `pg_label_ready()`), the order is not incomplete or cancelled and the address has no active outbound label. A marketplace-made label gets the marketplace's button (`trendyol-common-label.md`); the button posts the hidden `shipment-label-form` (outside the order form) to `shipment_label.php`.
- `pg_shipment_label_create($ship_to_id, $user_id)`: carrier = `shipping_methods.carrier`, else what the method code spells (`pg_shipment_label_carrier()`); driver = the method's linked one while ready (`shipping-method-label-link.md`), else the first shipping with the carrier, any for an unknown one (`pg_shipment_label_driver_pick()`). The shipment is `pg_shipment_label_shipment()`: weights and sizes are pounds and inches in `products`, in kg and cm (summed weight, largest side; else 1 kg / 10 cm); TR `state_code` from `pg_sales_map_match_region()`.
- Check and service call run under the address's lock (`pg_shipment_label_lock_name()`, no wait): a create meeting another is refused `busy` (API 409 `label_busy`), never a second label.
- A refusal or an unclear answer writes nothing and logs; an unclear one says to check the service first. On success: the document (asked once more through `label()` when create sent none) is stored, the row inserted, and the tracking number recorded only through `pg_shipment_record()` with `pg_label_shipment_options()`.

## When does a file go?
- `pg_shipment_label_expires_at()`: 30 days after the parcel's `delivered_at` (same `ship_to_id` and `number`) or the label's `cancelled_at`, 90 days after `created_at` at the latest. `pg_shipment_label_sweep_daily()` in `job.php`, once a day (state file `data/temp/shipment_label_sweep.txt`), at most 500 rows a run: file unlinked, `label_removed_at` set, row kept.
- Erasure (`pg_privacy_erase_tables()` `shipment_labels`, anonymise): `_pg_privacy_erase_apply_shipment_labels()` removes the files, then `label_file` is blanked; the rows stay (they name nobody). `delete_order.php` deletes files and rows (`pg_shipment_labels_delete_for_order()`).

## What came after?
- Bulk create and print, Cancel Label, return labels: `shipment-label-bulk.md` (plan step 3b). Automatic: `shipment-label-auto.md`. Pickup: `shipment-label-pickup.md`. The API: `shipment-label-api.md`. A marketplace's own label: `trendyol-common-label.md`.
- Tests: `tests/shipment_labels_test.php` (stub driver), `shipment_label_create_lock_test.php`.
