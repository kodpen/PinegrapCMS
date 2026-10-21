---
name: marketplace-order-import
description: Rules for importing marketplace orders (includes/api/outbound/orders.php): package as unit, map row first, side effects excluded, unmatched lines, relay emails.
---

## What is the unit of import?
- The unit is the PACKAGE, not the order: `marketplace_order_map` is UNIQUE on `(account_id, remote_package_id)`. Keying on the order number would count a second package of the same order as a duplicate.

## In what order is the map row written?
- The map row is written BEFORE the order. There is no transaction; on a retry this is the only thing preventing the order being written twice.
- A half-finished import = an `importing` row whose `order_id` is 0.
- An `imported` row whose `order_id` is 0 = a package already cancelled when first seen; no order is written for it (`marketplace-order-status-sync.md`).

## Which checkout side effects are used?
- The checkout page's side effects are NOT used. The three wanted: stock decrement, `pg_marketplace_product_changed()`, `create_notification('new_order')`.
- Email / auto-membership / points / gift card / campaign / QUOTES are deliberately left out.
- `process_order_cancellation()` is not used for import either: it attempts a refund on an order that never passed the gateway.

## What happens with unmatched or code-matched lines?
- One unmatched line makes the WHOLE order rejected: an order missing a line shows an incomplete shipment and decrements stock from the wrong product.
- A product matched by code is mapped at that moment; otherwise its stock change would never enter the queue and the product drifts from its first sale.

## How are customers matched?
- Persons are NOT matched by relay email addresses (`mp_order_email_is_personal()`): marketplaces issue a relay address per order; matching on it would write every order to one customer.

Related: docs/memory/data/orders-table-write-traps.md
