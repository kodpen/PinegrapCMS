---
name: product-group-copy
description: Product group copies (duplicate_product_group.php, pg_catalog_copy_group()): product copy links, Recycle Bin, duplicate notice, default options
---

## Which code copies a product group?
- `duplicate_product_group.php` (Duplicate Product Group, also opened by the variant set Duplicate button on the product screen) can copy subgroups and products too.
- The Catalog Manager's copy and paste calls `pg_catalog_copy_group()` (`includes/explorer/catalog_pages.php`): subgroups are copied, products are linked by reference, never duplicated.

## Which groups is a product copy linked to?
- Only the new groups. `duplicate_product()` copies every `products_groups_xref` row of the original, so `duplicate_product_group.php` deletes the fresh copy's rows right after the call and then links it to each new group it was in. A product met again in the same run reuses its copy and keeps its links.
- Symptom it fixed: with "Also duplicate Products" every copy was also listed in the original group (and any other group of the original), so a duplicated variant set left the original with two products per option combination.

## What happens to records in the Recycle Bin?
- Both paths skip them: subgroups with `pg_catalog_bin_live_sql('product_groups')`, products by joining `products` with `pg_catalog_bin_live_sql('products')`, the same filter Edit Product Group and the Catalog Manager map use.
- Before, binned subgroups came back as live, disabled groups (binning writes `enabled = 0`), and binned products were linked to the copy and reappeared there when restored.

## Why does Edit Product Group say "you are now editing the duplicate"?
- `duplicate_product_group.php` calls `remove_form()` on the `edit_product_group` liveform before `add_notice()`; the reverse order wiped the notice before the redirect (`ui/liveform-notices-trap.md`).

## Does the copy keep an attribute's default option?
- Yes, both paths copy `product_groups_attributes_xref.default_option_id` as a whole number. The Catalog Manager copy used to drop it, so the copied set showed no preselected option on Edit Product Group and the product page.
