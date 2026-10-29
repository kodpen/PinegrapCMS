---
name: product-bundles-storefront
description: Bundles in the catalog widgets (#2902): __is_bundle, __bundle_saving_formatted, is_bundle / has_bundle_saving flags, the product.bundle_items list, designer twins
module: bundles
---

## Which catalog tokens does a bundle have?
- `__is_bundle` (`1`/`0`) and `__bundle_saving_formatted` in `catalog_listing` and `catalog_item_view`, found and not-found maps alike: `_pg_catalog_bundle_values()` (`includes/fn/widgets_catalog.php`).
- The saving is `pg_bundle_saving()` (`includes/fn/bundles.php`): the components' prices × quantities less the price the visitor pays (an offer included, `round()`), never negative; `''` without a saving, written like `__discount_amount_formatted`.
- The contents come from `pg_bundle_contents_of($ids)`: one query for every bundle on the page (`pg_bundle_ids_among()` first). A switched-off component counts in the saving, is not listed.

## How do the "Show only when" flags work?
- `is_bundle` and `has_bundle_saving` (while the saving token holds a value), in `SW_VISIBILITY_FLAGS` of both kinds.
- Listing: `_pg_catalog_card_templates()` marks the four card flags (rating and bundle) and keeps the marked card as `'marked'`; a plain product takes `rated`/`unrated`, a bundle's card is resolved once per state by `_pg_catalog_card_for()` and kept. A group card draws no bundle element. The no-products check reads `'marked'`: a card drawn only for bundles is still a card.
- Product page: marked before the split, resolved with the rating flags. A variant switch keeps the bundle state of the product shown first (`pg_civ_variants.js` does not switch it).
- `check_bindings.php` finds flags in array literals only: the detail view lists `is_bundle` / `has_bundle_saving` as keys of `$_civ_flags`.

## What does the contents list give?
- `product.bundle_items` (`widgets_lists_product.php`, family `product`, key `__id`, both catalog kinds): `__bundle_item_name` (SKU), `__bundle_item_short_description`, `__bundle_item_quantity`, `__bundle_item_url` (detail page of `pg_sw_catalog_detail_page_name()`, `#` without one), `__bundle_item_image_url`. Rows: `pg_sw_list_product_bundle_rows()`; a plain product lists nothing. `pg_sw_list_product_read()` takes the contents the renderer already read (4th argument).
- Designer: palette item "Contents of the bundle", starter as a `ul` of quantity badge and linked name, `SW_LIST_SOURCES` twin, row previews; token group "Bundle" in both catalog palettes.

## Why is a bundle's stock a cache? (owner, #2425)
- About thirty readers use `products.inventory_quantity` (catalog, `check_inventory()`, feeds, marketplaces, API, stock notices, dashboards); with the cache they all stay as they are. Also decided there: no nesting or choice inside a bundle, a component in a bundle cannot be deleted, one invoice line with the bundle's tax rate, no marketplace listing in the first version.

Tests: `tests/catalog_bundle_tokens_test.php`, `tests/widget_lists_product_test.php`, `tests/bundles_test.php`. Related: [[product-bundles]], `api/api-product-bundles.md`.
