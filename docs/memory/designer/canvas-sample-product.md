---
name: canvas-sample-product
description: The sample product the designer canvas draws in the catalog listing, product page, wishlist, order form and search results (CS4a, #9253); the tag cloud's samples; catalog block previews
---

## What does the catalog family draw on the canvas?
- One product (`_sdProductSample()` in `style_designer.src.js`) for widgets whose `regionType` is in `_SD_PRODUCT_SAMPLE_REGIONS`: `catalog_listing`, `catalog_item_view`, `wishlist`, `order_form`, `search_results`. It is the sample order's first line (`canvas-sample-order.md`): Linen Shirt, PRD-1001, 450.
- `_sdSampleRecord(node)` picks the order or the product by the widget's `regionType`; `_displayValueForBinding()` and `_sdResolveAttrTokens()` read it after the HTML previews, before every other map.
- Canvas only: never in a tree, never on the site.

## Which values agree?
- Price 450, original 500, discount 50 = 10%, range 450 – 520, reference price 480; stock 12 of 12, three variants, category Shirts. `tests/designer_canvas_product_sample_test.php` checks the prices and that every yes/no is `'1'` or `'0'`, as `widgets_catalog.php` writes them (a `'0'` is not empty: a condition reads it with eq 1).
- The flags of `_SD_FLAG_TOKENS` (`__has_discount`, `__has_related`, `__has_active_filters` …) stay out of the record, so a discount or related block follows the canvas state.

## Which value is drawn though the page leaves it empty?
- `__out_of_stock_message`: the page writes it only for a product off the shelf; the canvas draws the notice on the in-stock sample so the designer can place and style it, as the cart's stock warning preview does.

## Which slots draw the page's markup?
- `_SD_HTML_PREVIEW_TOKENS`: `__group_tree_html` (a category list), `__filters_button`, `__clear_filters_button`, `__attribute_filters_html`, `__filters_offcanvas` (drawn open), `__variant_picker` (a Size select), all disabled.
- The tag cloud's values are in `_SD_SHORT_LABEL_TOKENS` (search page name, tag name, keyword, count, size, weight, total).

## What is left?
- `canvas-sample-gaps-catalog.md`.
