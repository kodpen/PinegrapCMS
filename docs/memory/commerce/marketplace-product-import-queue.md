---
name: marketplace-product-import-queue
description: The product import queues the products it updated for the marketplaces in one call at the end, after the bundle refresh, without webhooks
module: ecommerce
---

## Does the product import send its prices and stock to the marketplaces?
- Yes, through the queue. A file with a `price`, `extra_shipping_cost`, `inventory`, `inventory_quantity` or `enabled` column (`pg_products_import_marketplace_columns()`) collects every product it updates; after `pg_bundle_refresh_all()` the import calls `pg_marketplace_products_changed($ids)` (`includes/fn/ecommerce.php`).
- A file with only texts queues nothing. A new product has no marketplace mapping yet, so only updated products are collected.
- Before #4772 the import queued nothing, so Trendyol, n11 and Merchant Center kept the old price and stock until the product changed another way (#4720).

## Why not pg_marketplace_product_changed() per row?
- That one refreshes the bundles holding the product first; the import refreshes every bundle once at the end, and a bundle that changes queues itself in `pg_bundle_refresh()`. `pg_marketplace_products_changed()` skips the refresh, drops ids that are not positive numbers and repeats, and calls `mp_enqueue()` per product (one row per mapped account; no network call).

## Does the import announce webhooks?
- No: the bulk catalogue rule keeps `inventory.changed` silent for an import; only the marketplace queue is written.

Related: docs/memory/commerce/marketplace-outbound-layer.md
