---
name: products-list-bulk-writes
description: Products list bulk Delete Selected and Enable/Disable: the bundle component refusal it reports, and the SEO stale mark a change of the enabled state needs
---

## What does Delete Selected do with a bundle component?
- `edit_products.php` checks `pg_bundle_is_component()` before binning or deleting (both `pg_catalog_bin_product()` and `pg_pb_delete_product()` refuse one) and counts it apart.
- After the moved/deleted notice it marks an error with `pg_bundle_bulk_delete_refusal($count)` (includes/fn/bundles.php): two literal keys chosen by the count, empty for 0. Before, a refused component stayed with no reason and an all-component selection came back with no message.
- The Catalog Manager delete (`includes/explorer/handle_catalog.php`) is a separate screen.

## Which product writes mark the SEO analysis stale?
- The product screen save (`product_builder.php`) always sets `seo_analysis_current = 0`; the list's quick edit does when `enabled` changes.
- Modify Selected's Enable/Disable (`$sql_enabled` in `edit_products.php`) and the Catalog Manager bulk enable (`includes/explorer/handle_products_bulk.php`) do too.

## Why does the enabled state matter?
- `seo.php` reads a product's `searchable` from `enabled`; the keyword check applies only to searchable records.
- Only stale rows are recalculated: without the mark, "Products With a Weak SEO Score" and the SEO column kept the old state's score until the 7-day full refresh.
- The API rescores at once instead (`api/api-catalogue-writes.md`).
