---
name: products-variant-model
description: products/product_groups access and price units, the no-separate-variant-table model and its xref tables, external API field source, and the no per-row queries list rule.
---

## Access, edit URLs and price units?
- Reachable only when `ECOMMERCE === true`. `products`: `edit_product.php?id={id}`; `product_groups`: `edit_product_group.php?id={id}`.
- Price columns are stored in kuruş (minor units); the external API also returns kuruş (`api_money()`); the panel divides by 100 for display.

## How are variants modeled?
- There is NO separate variant table. Every `products` row is one purchasable thing; the same product in another colour/size is a separate row. `product_groups` keeps them together:
- `products_attributes_xref` (product_id, attribute_id, option_id, sort_order): which choices the row corresponds to.
- `products_groups_xref` (product, product_group, sort_order): which groups the row is in.
- `product_groups_attributes_xref` (product_group_id, attribute_id, default_option_id, sort_order): the group's axis order. It has NO unique key: the same attribute can be linked to a group twice (it is in the sample catalogue). Read each attribute once per group.
- `product_attributes` (`name` = internal, `label` = shown to customer) / `product_attribute_options` (`no_value` = the "nothing selected" row, NOT a variant).

## Which fields does the external API return?
- The `api_*_present()` functions in `includes/api/resources/products.php` and `product_groups.php` are the single source; do not repeat the field list elsewhere.
- Price goes out in kuruş (`api_money()`), panel `/100`.

## List endpoint query rule
- No per-row queries in the list endpoint: it returns up to 250 rows. Image/attribute/group reads go through batch helpers such as `api_product_extras()` and `api_product_group_extras()` (`WHERE id IN (...)`); the presenter reads from the prepared array.

Related: docs/memory/commerce/variant-price-rules.md, docs/memory/commerce/product-tax-rate.md
