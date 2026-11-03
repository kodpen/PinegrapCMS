---
name: product-group-copy
description: Product group copies (duplicate_product_group.php, pg_catalog_copy_group()): product copy links, Recycle Bin, duplicate notice, default options, variant set form and price rules
module: variant_set_copy
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

## Can a group be copied into itself?
- No. `pg_explorer_handle_catalog_paste()` checks every group of a copy paste with `pg_catalog_group_is_within()` (`includes/explorer/catalog.php`, a parent walk over the group map) before anything is copied, and refuses with "A product group cannot be copied into itself."; the panel shows it as the error toast. A cut already had the same refusal.
- As a second guard `pg_catalog_copy_group()` reads a group's children before it inserts the copy, and the depth cap of 30 stays.
- Symptom it fixed: Paste on the group itself, Ctrl+V inside it or Ctrl/Alt-drag onto a subgroup copied the fresh copy into itself again and again, leaving about 30 nested groups.

## Does a copied variant set keep its form and price rules?
- Yes (PR #5393). Both paths call `pg_variant_set_copy()` (`includes/fn/variant_set_copy.php`) after the group row: it copies `form`, `form_name`, `form_label_column_width`, `form_quantity_type` and `variant_price_rules` (each only where the row has it) and the template (`form_fields` with `form_type = 'product_group'`, options, triggers, ids remapped).
- Product copies from Duplicate Product Group: `duplicate_product()` drops `template_field_id`, so their generated fields are matched by name to the original's (`pg_variant_set_copy_field_links()`, tested in `tests/variant_set_copy_test.php`) and linked to the template copy. The Catalog Manager links the same products, whose fields stay on the original template.
- Symptom it fixed: the copy had no form template, no form settings and no price rules; the next template edit on it added every generated field to the product copies a second time.
