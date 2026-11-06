---
name: product-group-edit-checks
description: Edit Product Group, Duplicate Product Group and Featured and New Items (PR #5551): the Recycle Bin left out of status changes and lists, the parent checked on the server, the duplicate form cleared
---

## Does enabling a group switch on what is in the Recycle Bin?
- No. `update_product_group_status()` walks the subtree with `get_product_groups(array('id' => …, 'live' => true))`, which adds `pg_catalog_bin_live_sql('product_groups')` to the child query, and reads the products through `products` with `pg_catalog_bin_live_sql('products')` (a binned product keeps its `products_groups_xref` row for the restore).
- Binning writes `enabled = 0` because the storefront reads only `enabled`; the restore brings back `recycled_enabled`. Before, enabling a parent set binned sub-groups and products to `enabled = 1`, and they opened again on their catalog address.
- Every caller gets it: the edit screen, the API, `includes/panel/commerce.php`, `variant_set_action.php`, the Workspace.

## Can get_product_groups() loop for ever?
- No. It passes the ids above it on the walk (`seen`) and skips a child already among them, so a `parent_id` ring ends the walk.

## Is the parent of Create and Edit Product Group checked on the server?
- Yes. `pg_product_group_form_parent_ok()` (`includes/forms/product_group_form.php`, pure, tested in `tests/product_group_screens_test.php`) accepts 0, a live group from `pg_catalog_group_map()` or the parent the group already has, and refuses the group itself and its own subtree (`pg_catalog_group_is_within()`) with "A product group cannot be moved into itself.", as the Catalog Manager's cut does.
- Symptom it fixed: a group moved under this one while the screen was open was saved as its parent; the two formed a ring and dropped out of the catalogue.
- Create Product Group asks it with id 0 and parent 0; a parent binned or deleted meanwhile stored the new group outside the live tree.

## Why did Duplicate Product Group say "Sorry, something went wrong"?
- The form posts back to its own address, and the hidden `id` was printed through `output_field()`, which prefers the value an earlier visit left in the session; the posted id then differed from the address's and the screen refused. The hidden field is now written from the checked id, and `remove_form()` runs before the redirect, so the next Duplicate screen opens with its switches off and the address's group.
- The screen has a Cancel back to Edit Product Group; the first call of `duplicate_product_group()` reads `parent_product_group_id` with `?? ''`.

## Does a 100-character name keep its [1] on a copy?
- Yes. `get_unique_name()` cuts the base with `pg_unique_name_limit($type)`: 100 (`pg_name_max_length()`) for page, folder, product and product group, 0 for the rest. Before only pages were cut, and a product group copy lost its suffix in the column and got its source's name.

## Does Featured and New Items list binned records?
- No. `get_items_that_can_be_featured_and_new()` filters groups with `pg_catalog_bin_live_sql('product_groups')` and products with `pg_catalog_bin_live_sql('products')`; the save walks the same list.

## What does a Create or Edit Product Group save refuse?
- `pg_product_group_form_post_problems()` (pure, `tests/product_group_form_test.php`): a name blank once trimmed, a name or short description past 100 characters, a sort order of a ticked product or child group that is no whole number 0-65535 (`pg_product_group_form_sort_order()`). Before, '   ' was saved as a name and '-12' was stored as 0 by the smallint unsigned column.

## Can a group with sub-groups be deleted from Edit Product Group?
- With the Recycle Bin yes: the subtree goes there together, and the ask says so. Without the bin Delete is disabled with the reason beside it; no native alert().
- A binned group offers no Duplicate, and `duplicate_product_group.php` refuses it: a copy came out live without its (binned) sub-groups.
