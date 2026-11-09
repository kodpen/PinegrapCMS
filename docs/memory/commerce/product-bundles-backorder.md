---
name: product-bundles-backorder
description: A bundle's back order: the merchant's choice in products.bundle_backorder (11.0.176) and products.backorder as a cache of choice and components; what the screen, the refresh and a copy do
module: bundles
---

## Where is a bundle's Backorder tick kept? (#7746, decision #8365)
- `products.bundle_backorder` TINYINT(1), step 11.0.176 (`upgrade_2026_11_0_bundle_backorder()`), filled once from `backorder` for `bundle = 1` rows when the column is added; a rerun does not fill again (it would overwrite choices saved since).
- Read it only behind `pg_bundle_backorder_ready()`: the code lands before the upgrade.
- A plain product keeps its choice in `backorder`.

## What is products.backorder of a bundle?
- A cache: `pg_bundle_backorder($row, $backorder_allowed)` = choice && every tracked component takes back orders (`pg_bundle_stock()`). `pg_bundle_refresh()` writes it.
- A row without `bundle_backorder` (before the step) reads the cached `backorder` as the choice.
- Before #7746 the refresh read the cache as the choice: once a component refused back orders the bundle never took them again until it was saved.

## What does the product screen do?
- The save (`pg_pb_update_product()`) writes the tick to `bundle_backorder` for a bundle; the row's `backorder` is then set by `pg_bundle_refresh()`.
- `pg_pb_load_product()` shows a bundle's switch from `bundle_backorder`, not from the cache.
- A copy keeps the choice (`pg_product_copy_extra_sql()`).

## What stays as it was on a running site?
- A bundle whose cache was already lowered to 0 is filled with 0 and stays 0 until it is saved once: the lost choice cannot be recovered.
- Other writers of `backorder` (import, API, Workspace) set the cache only: the bundle's next refresh works it out from `bundle_backorder` again.
