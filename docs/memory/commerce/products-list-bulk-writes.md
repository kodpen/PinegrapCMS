---
name: products-list-bulk-writes
description: Products list and Catalog Manager bulk delete, enable and restore: bundle component refusal, restore limited to the bin, SEO stale mark and marketplace send
---

## What does Delete Selected do with a bundle component?
- `edit_products.php` checks `pg_bundle_is_component()` before binning or deleting (both `pg_catalog_bin_product()` and `pg_pb_delete_product()` refuse one) and counts it apart.
- After the moved/deleted notice it marks an error with `pg_bundle_bulk_delete_refusal($count)` (includes/fn/bundles.php): two literal keys chosen by the count, empty for 0. Before, a refused component stayed with no reason and an all-component selection came back with no message.
- The Catalog Manager delete (`pg_explorer_handle_catalog_recycle()`, `includes/explorer/handle_catalog.php`) does the same since #4016: before, an all-component selection answered only "Sorry, we could not accept your request."

## Which products does the Catalog Manager restore write?
- Only rows in the bin: the product restore has `AND (recycled = '1')` and counts, audits and deletes the bin record only when `mysqli_affected_rows()` > 0.
- Symptom it fixed (#4016): Undo of a Delete replays the restore on the whole selection; a bundle component the delete refused got `enabled = recycled_enabled` (default 0, or a stale value) and went off sale.
- The bin badge counts only the top binned groups (`pg_catalog_bin_top_group_count()`, `includes/explorer/catalog.php`), as the bin lists them.

## Which product writes mark the SEO analysis stale?
- The product screen save (`product_builder.php`) always sets `seo_analysis_current = 0`; the list's quick edit does when `enabled` changes.
- Modify Selected's Enable/Disable (`$sql_enabled` in `edit_products.php`) and the Catalog Manager bulk enable (`includes/explorer/handle_products_bulk.php`) do too.
- The Catalog Manager Publish/Unpublish (`pg_explorer_handle_catalog_enable()`, groups and products) and its restore (a product, or the variants restored with their set) do too (#4016): the nightly full refresh scores binned rows as unpublished.

## Which enabled writes queue a marketplace send?
- All of them, through `pg_marketplace_product_changed()`: a disabled product is listed with quantity 0 (`mp_product_row()`). Binning queues one at `enabled = 0`, so the Catalog Manager restore (product and variants), Publish/Unpublish and the Catalog Manager bulk enable (`$bulk_enabled !== ''`) queue one too (#4016). Before, the marketplace stayed sold out after a restore and kept selling after Unpublish.

## Why does the enabled state matter?
- `seo.php` reads a product's `searchable` from `enabled`; the keyword check applies only to searchable records.
- Only stale rows are recalculated: without the mark, "Products With a Weak SEO Score" and the SEO column kept the old state's score until the 7-day full refresh.
- The API rescores at once instead (`api/api-catalogue-writes.md`).
