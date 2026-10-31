---
name: product-builder-parent-group
description: The Product Builder's parent group list for a new variant group (PR #4309): no empty choice, opens on the top group; what a posted 0 still does
---

## What does the parent group list offer?
- Every product group outside the Recycle Bin (`get_product_group_options()`), the top of the catalogue (`pg_catalog_group_top_id()`) selected when the screen opens. There is no `-None-` option: a group always has a parent.
- The field is drawn only when the catalogue has groups (`pg_pb_has_product_groups()`); on an empty catalogue the screen shows an empty state instead.

## What if 0 or nothing is posted?
- The save hands `parent_id` to `pg_catalog_variant_set_create()`, which files the group under the top (`pg_catalog_group_parent_id()`, see `catalog-group-writer.md`). The old `-None-` option posted 0 and so already meant the top; removing it changed no saved result.

## Why was it changed?
- A `-None-` choice read as "no parent" (a second root the Catalog Manager, storefront and feeds never reach) while it saved under the top group.

## Which test covers it?
- `tests/product_builder_parent_group_test.php` (PR #4309; source: no `value="0"` option, the top selected); `tests/catalog_bin_test.php` pins the same call.
