---
name: api-catalog-recycle-bin
description: How the external API treats products and product groups in the catalogue's Recycle Bin: what is listed, what a group's product_ids and in_use count
---

## Which columns mark a binned record?
- `products.recycled` and `product_groups.recycled` (`'1'` in the bin). `api_catalog_bin_columns()` (`includes/api/resources/products.php`) is true only when both columns exist; every filter below is skipped without them.
- Binning (`pg_catalog_bin_statements()`, `includes/fn/ecommerce.php`) writes `enabled = 0` and keeps the old value in `recycled_enabled`; a product's rows in `products_groups_xref` and `products_attributes_xref` are kept so that restoring it puts it back where it was.

## What do the catalogue routes show of a binned record?
- `GET /products` and `GET /product-groups` do not list it; `GET /products/{id}` and `GET /product-groups/{id}` still read it, with `recycled: true`.
- Filing a binned product into a group (`POST /product-groups` `product_ids`, `POST /product-groups/{id}/products` `add`) is refused with 422, as if there were no such product (`api_product_group_product_ids()`).

## Does a group's product_ids include binned products?
- No (since #1046). `api_product_group_extras()` joins `products_groups_xref` to `products` with `products.recycled = '0'`, so `product_ids` (the group's variants) leaves a binned product out, and `attributes[].options[].in_use` no longer counts the options only it carries. Symptom it fixed (#876): a connector building variants from `product_ids` published a product the shop no longer sells, and a size only that product had showed as in use.
- The inner join also drops link rows whose product no longer exists.
