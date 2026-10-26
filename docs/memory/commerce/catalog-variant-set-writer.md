---
name: catalog-variant-set-writer
description: pg_catalog_variant_set_create(): input array, shared check, prices in minor units, callers (product screen, POST /product-groups/variant-sets), what it skips
---

## What writes a variant set?
- `pg_catalog_variant_set_create($data, $user, $opts)` in `includes/fn/ecommerce.php`: one 'select' group (`pg_pb_create_group()`), one product per row filed only in it (`pg_pb_create_product()`, which starts each price history), each row's barcode (`pg_pb_apply_barcode()`), then the rules kept on the group (`pg_variant_price_rules_store()`). It loads `product_builder.php` when needed.
- Returns `group_id`, `product_ids`, `name` (short description, else name) and `errors`; nothing is written when `errors` is not empty.
- `$data`: the group's fields (name, short_description, full_description, details, parent_id, enabled, code, keywords, images, address_name, title, meta_description, attribute_meta, form, form_name, form_label_column_width, form_quantity_type), `common` (the columns every variant shares), zone_ids, `price_base` and the variant prices in integer minor units, `price_rules` (canonical or null), `variants` (rows shaped as `pg_pb_decode_variants()` gives them).
- `$user`: null keeps `USER_ID` (the screen); an array with id or a user id stamps the group and every product. `$opts`: ai_fields, submit_form (absent: `$_POST`), barcode_auto (absent: the screen's switch), price_source.

## Which checks are shared?
- `pg_catalog_variant_set_check($data)` needs no database: a group name, at least two rows, a SKU on each, every price and the base a whole non-negative int (`pg_catalog_variant_set_minor_units()`). The screen never trips it (add_product.php requires the name; group mode means two rows).
- A row's price: `pg_catalog_variant_set_price()`, the rules through `pg_variant_price_compute()` unless the row is `price_manual` or there are no rules. Never `(int)`.
- `pg_catalog_variant_set_product()` builds one row and its links without a query (tested).

## Who calls it?
- The product screen's group mode, `pg_pb_save_new_product()`: it turns the posted prices into cents (`pg_pb_price_to_cents()`), reads the rules from the form (`pg_pb_price_rules_from_post()`) and passes `ai_fields`. The single mode and the image code template stay in the screen.
- `POST /product-groups/variant-sets` (`api_product_groups_variant_sets_create()`, products:write, dry_run): 409 `name_taken` for the group name or a SKU, the parent (`api_product_group_parent_or_fail()`, shared with POST /product-groups), images, rows (`api_product_group_variant_rows()`: lengths 100/100/50, a SKU sent twice, option pairs through `api_products_create_attributes()`), rules normalized and owned; a row without a price needs rules. Then the shared check, `api_dry_run_stop()`, the writer with `$app['owner']`, `submit_form` empty, `barcode_auto` false, `price_source` 'api'. Variants start from `api_products_create_defaults()`; the axes are the attributes in the order the rows name them.

## What does it not do?
- No `product.created` or `product_group.created`, no marketplace call, no SEO rescore: the screen never made them for a set, and a new product is matched to no listing. Revisit with the Workspace `variant_set` kind (plan slice 4b).

## Which test covers it?
- `tests/catalog_variant_set_test.php`: the check, the price, one built row, and from the source that the screen and the route write through it with the rehearsal above the write.
