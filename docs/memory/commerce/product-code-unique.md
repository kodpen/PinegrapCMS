---
name: product-code-unique
description: The product screen refuses a product code (products.name) another product holds, on create and edit, with a live check on the field; variant sets, Recycle Bin, what still renames
---

## What does the product screen do with a code another product has?
- `pg_pb_validate_product_post()` (`product_builder.php`, run by `add_product.php` and `edit_product.php` before anything is written) refuses it with `output_error()` and "Go back", which keeps what was typed. The text names the holder by its short description, else `#id` (`pg_product_name_taken_text()`).
- The codes checked are the ones the post writes (`pg_pb_posted_product_names()`):
  - on edit, the product's own code, and only when it differs from the stored one, so a catalogue that already holds a duplicate still saves;
  - on create, the typed code or a single matrix row's own code;
  - for a variant set, every row's code. The typed code is then only the rows' prefix and is not checked. Two rows of one code are refused too (`pg_product_names_repeated()`, case aside).
- `pg_product_name_holder($name, $except_id)` (`includes/fn/ecommerce.php`) compares by the column's collation, like the API's `api_products_name_or_fail()`: a case difference is the same code. A product in the Recycle Bin still holds its code, and the text says so.

## What does the field do while typing?
- `assets/js/product_name_check.js`, loaded after `product_builder.js` on the product screen, asks `api.php` `product_name_check` 400 ms after typing stops and on change.
- It writes the holder and an "Open it" link into the field's `.invalid-feedback`, and stops a save while the typed code is the one found taken.
- The edited product's id comes from the input's `data-pg-name-except`. The field is not checked when the code is unchanged or in variant set mode (the `.pg-pb-group-only` hint is visible).
- `product_name_check` is exempt from the general gate: role 0–2 or `manage_ecommerce` with the token, as the barcode actions are (`core/panel-action-table.md`).

## Why?
- Before this (October 2026), create renamed a taken code to `CODE[1]` (`get_unique_name()` in `pg_pb_create_product()`) without telling anyone, and edit wrote any code. The owner asked that the code field never take a code another product has.
- Two products with one code cannot be told apart by an import, a marketplace match or an order line.

## What still renames?
- `pg_pb_create_product()` keeps `get_unique_name()` as the last guard for callers without this check: the Workspace, the designer's demo products and the API, which refuses first anyway. Product group names (`pg_pb_create_group()`) are still made unique by renaming.
