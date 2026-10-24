---
name: marketplace-order-import
description: Rules for importing marketplace orders (includes/api/outbound/orders.php): package as unit, map row first, side effects excluded, product matching, unmatched lines and their retry, relay emails.
---

## What is the unit of import?
- The unit is the PACKAGE, not the order: `marketplace_order_map` is UNIQUE on `(account_id, remote_package_id)`. Keying on the order number would count a second package of the same order as a duplicate.

## In what order is the map row written?
- The map row is written BEFORE the order. There is no transaction; on a retry this is the only thing preventing the order being written twice.
- A half-finished import = an `importing` row whose `order_id` is 0.
- An `imported` row whose `order_id` is 0 = a package already cancelled when first seen; no order is written for it (`marketplace-order-status-sync.md`).

## Which checkout side effects are used?
- The checkout page's side effects are NOT used. The three wanted: stock decrement, `pg_marketplace_product_changed()`, `create_notification('new_order')`.
- Deliberately left out (header of `includes/api/outbound/orders.php`): the receipt e-mail (the marketplace sent one), auto-registration, reward points, gift card issuance, e-mail campaigns, offers (they would re-price the sale against rules the marketplace never knew) and payment.
- `process_order_cancellation()` is not used for import either: it attempts a refund on an order that never passed the gateway.

## What happens with unmatched or code-matched lines?
- One unmatched line makes the WHOLE order rejected: an order missing a line shows an incomplete shipment and decrements stock from the wrong product.
- Match order (`mp_order_match_product()`): mapping row by `remote_code = code`; for a barcode-keyed provider (Trendyol) `product_barcodes.barcode = code`; `products.name = sku` when the line has one (Trendyol `stockCode`); `products.name = code` last (n11).
- A product matched without a mapping is mapped then under `code`; otherwise its stock change would never enter the queue and the product drifts from its first sale. `INSERT IGNORE`: a product already mapped under another code (a second barcode) keeps its row; a plain INSERT would end the job on the duplicate key (PR #1452).

## How is a refused package tried again?
- A `failed` row with `order_id` 0 (refused before any order was written) is re-claimed when the feed hands the package back: one `UPDATE ... SET status='importing' WHERE status='failed' AND order_id=0`; only the pass that changed the row writes (`mp_import_claimed()`).
- An `importing` row with `order_id` 0 is NOT retried: it may be a write in progress or a half-written order.
- The package only comes back if its `lastModifiedDate` falls in the window, so the Imported orders card has "Try again" (`mp_action=retry_orders`, shown while such rows exist): it moves `last_order_pull` back to the oldest one's `remote_timestamp` (the fallback `imported_timestamp` minus 3 h), never forward. `remote_timestamp` is on the pull cursor's own scale; an extra margin would read packages no pull ever read and import them as new orders. Re-reading is safe: imported packages are skipped.
- The pull's cursor write is compare-and-set on the cursor it started from, so a Try again pressed during a pull survives (#1021).
- Symptom before the fix (#1021): after matching the product the package stayed "Failed" for ever; the import answered `skipped` and the queue's "Try again" only reset `marketplace_sync_queue`.

## How are customers matched?
- Persons are NOT matched by relay email addresses (`mp_order_email_is_personal()`): marketplaces issue a relay address per order; matching on it would write every order to one customer. Trendyol's buyers arrive as `pf+…@trendyolmail.com`.

Related: docs/memory/data/orders-table-write-traps.md
