---
name: widget-lists-product
description: Product lists in the catalog detail and listing widgets (W9): images, attributes, products bought together; where rows are read, group cards, the cross-sell block they replace, what is left
---

## Which product lists exist?
- `pg_sw_list_product_sources()` (`includes/fn/widgets_lists_product.php`, merged into `pg_sw_list_sources()`), family `product`, key `__id`, no arg; offered by `catalog_item_view` (#1752) and, but for `product.related`, by `catalog_listing` (#1768; `pg_sw_kind_lists()`).
- `product.images`: the main image first, then `products_images_xref` by file name, each address once. Token `__gallery_url`.
- `product.attributes`: `products_attributes_xref` → attribute label (its name without one) and option label, by attribute id and option sort order; an option marked `no_value` or empty gives no row. Tokens `__attr_name`, `__attr_value`.
- `product.related`: `_civ_get_cross_sell_items($pid, $detail_page, 12)` (ordered together, else the product's groups). Tokens `__related_name`, `__related_short_description`, `__related_url`, `__related_image_url`, `__related_price` (price paid, plain text), `__related_original_price` (sticker price, empty without a discount).
- Other values in a row (the product's `__name`, `__short_description` …) come through carriers, as in every list.

## Where are the rows read?
- The renderer asks `pg_sw_list_wanted('product')` (the sources its tree drew), reads only those for the products shown (`pg_sw_list_product_read()`: one query per source for the whole page, at most 100 rows a product; related products one cross-sell lookup each, so the listing does not offer them) and publishes them; the provider reads the published rows. A tree without a product list reads nothing more. The pure builders (`pg_sw_list_product_*_rows()`) are tested in `tests/widget_lists_product_test.php`.

## How does a group card in a listing stay empty?
- Product and group cards share one template and both have `__id`. `pg_sw_list_key_guard('product')` adds `^^__row_type^^` as the span's second carrier; a span whose value reads `group` takes no key, so a group never shows the rows of a product with its id. In the editor a listing's lists must sit in the main loop (`_sdListNeedsMainLoop()`), as in a form list.

## What happens to the cross-sell block?
- A `product.related` list takes its place: with the list in the tree `__cross_sell_html` is drawn only where its token is placed, so the block is not appended as well. `cross_sell_enabled` off switches only the block, never a placed list. The detail page a related product links to is read once for both (`$_civ_detail_pid`).

## What does the editor give?
- Palette "Lists in a record": three more items; starters are real elements (`_sdProductListStarter()`): images as `col` pictures in a `row g-2`, attributes as `dt`/`dd` in a `dl.row`, related products as cards in a `row g-3` with `limit: 4`. The list panel shows no field row for a source without an arg. `_swBindLabel()` names a row token in every kind.

## What is not done yet?
- A variant picked on the page (`pg_civ_variants.js`) does not redraw the lists: they show the product the page opened with.
- An empty list leaves its surroundings (a heading over related products): `catalog_item_view` has no pg-hide-if-empty rerun (open owner question "pg-hide-if-empty everywhere").
- Group images (`product_groups_images_xref`), keywords, the products of a group: not offered. A browser round is owed.
