---
name: api-product-bundles
description: Product bundles in the external API: the bundle block on Product, the bundle fields POST /products/{id} writes, what it refuses, the price batch and inventory answers for a bundle
---

## What does a product read carry?
- `bundle`: null on a plain product (and on every product before the bundle schema), else `{pricing: fixed|sum, discount_bp, items: [{product_id, quantity}]}` in the contents' order. `api_product_bundle()` builds it; `api_product_extras()` reads the contents of a whole page in one query; `ProductBundle` is its own object so the field can be nullable.
- `inventory` of a bundle is the cached stock its components allow (`commerce/product-bundles.md`); the field shape is unchanged.

## What can a write change?
- Only `POST /products/{id}`: `bundle`, `bundle_pricing`, `bundle_discount_bp` (0–10000), `bundle_items` (replaces, max 50, quantity 1–9999). It mirrors the product screen's Bundle card: switching off empties the contents; after the write `pg_bundle_refresh()` brings stock and a sum price in line. Create takes no bundle field (the screen's card is edit-only).
- `api_products_bundle_items_read()` refuses instead of cleaning (422 `bundle_items`): an item without whole `product_id`/`quantity`, a quantity outside the bound, the bundle itself, a product named twice. `pg_bundle_items_problem()` then refuses a missing or unfit component (a bundle, donation, gift card, recurring product).
- 409 `product_in_bundle` when a component is switched to a bundle; 422 `bundle_items` when contents are sent to a product that stays plain; 422 `price` when a price is sent to a `sum` bundle (the refresh would overwrite it); 503 before the schema.

## What do the stock and price batches answer for a bundle?
- `POST /products/{id}/inventory` 409 `bundle`, the batch status `bundle` (#2707).
- `POST /products/prices` reports a `sum` bundle as status `bundle` and writes nothing for it; a `fixed` bundle is priced like any product.

Tests: `tests/api_product_bundle_test.php`. Related: `commerce/product-bundles.md`.
