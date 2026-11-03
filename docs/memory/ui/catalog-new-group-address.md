---
name: catalog-new-group-address
description: Catalog Manager New Product Group takes its address from its first name; delete badges carry the count in the sentence, the bin progress says so, New image hidden in the catalog
---

## Which address does a group made with New Product Group get?
- The one its first name makes. `view_folders.php` creates the group under the placeholder `L.new_product_group` and opens the rename box; `pg_explorer_handle_catalog_rename()` rewrites `address_name` with `prepare_catalog_item_address_name($name, $id, 'product_group')` while `pg_explorer_catalog_address_follows_name()` holds: the name is still `lang('New Product Group')` and the address is the placeholder's slug (`pg_catalog_address_slug()`, `includes/fn/content.php`) with only `[n]` suffixes after it. Both pure, tested in `tests/catalog_new_group_address_test.php`.
- Any later rename keeps the address: moving a live URL breaks links to it; the edit screen changes an address on purpose.
- Symptom it fixed: every group made here was published as `/Yeni_Urun_Grubu[n]`, and a copy became `Yeni_Urun_Grubu[1][1]`.
- The catalog's rename box has `maxlength="100"` (the name columns), so a typed name is never refused and the group left under the placeholder.

## How do the delete dialogs write a count?
- Through a sentence with the number in it: `L.count_folders`, `count_pages`, `count_files`, `count_product_groups`, `count_products`, `count_short_links` (`'{var:1} folder(s)'` …, Turkish `'{var:1} klasör'`), filled with `fmt()`. A number before a plural label read "1 Ürün Grupları".
- The progress line says `L.moving_to_bin` when the mode is `bin`, `L.deleting` otherwise.

## Is New image offered in the catalog?
- No. `#new_image_item` gets `d-none` in the catalog, like upload and create file; before it stayed as a greyed entry.
