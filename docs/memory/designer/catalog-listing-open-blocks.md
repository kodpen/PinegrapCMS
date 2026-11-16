---
name: catalog-listing-open-blocks
description: Catalog listing blocks opened (CB6b): the category tree and the filters offcanvas drawn on the canvas from twins of pure PHP builders, settings only
module: widgets_catalog
---

## What did CB6b open?
- Step CB6b of `plans/designer-closed-blocks-steps.md`; the census: [[canvas-closed-blocks-widgets]]. The catalog detail page's blocks: [[catalog-detail-open-blocks]].
- The sections `group_tree`, `filters_offcanvas` and the legacy `attribute_filters`, and the tokens `__group_tree_html`, `__filters_offcanvas`, `__attribute_filters_html`: settings only, drawn true. They depend on the request: the visitor's group, the attributes of the products in scope, the filters picked, the address's parameters.
- The Filters and Clear Filters buttons were already design elements in the starter, on the actions `open_filters` and `clear_filters` ([[system-widget-bindings]]); `__filters_button` and `__clear_filters_button` stay old tokens with their previews.

## What does the site build them with?
- Pure builders in `includes/fn/widgets_catalog.php`: `pg_catalog_group_tree_level()` (one level; `_pg_render_catalog_group_tree()` reads the groups and passes itself for the next level), `pg_catalog_price_filter_html()`, `pg_catalog_stock_filter_html()`, `pg_catalog_attribute_filters_html()` (a filter link's query string from a callable), `pg_catalog_filters_offcanvas_inner_html()`. The listing's output is unchanged: the code moved out of `_render_system_widget_catalog_listing()`.

## What does the canvas draw?
- Twins in `style_designer.src.js`: `_sdCatalogGroupTreeHtml()`, `_sdCatalogPriceFilterHtml()`, `_sdCatalogStockFilterHtml()`, `_sdCatalogAttrFiltersHtml()`, `_sdCatalogFiltersInnerHtml()`, composed by `_sdCatalogSectionHtml(section, cfg)` with sample groups (Shirts with Linen and Oxford, the visitor in Shirts) and attributes (Colour, Size; Natural picked).
- An empty section box in a catalog listing (`buildNodeEl()`, `_sdCatalogSectionSample()`) draws that sample, inert, with a line saying the site fills it, instead of the label box. The offcanvas is drawn open in place; on the site it is the hidden left panel the Filters button opens.
- The widget's settings decide what is drawn, as on the site: price, stock (its default ticked) and attribute filters (check boxes or buttons) each by their switch; nothing for `group_tree` with group navigation None; all three filters off: the section fades as before ([[catalog-binding-disabled-no-output]]).

## Does a running site change?
- No: same markup, same filters, same addresses.

## How was it checked?
- `tests/catalog_listing_closed_blocks_test.php`: each twin byte for byte against its builder for a catalog with sub groups and a select group, both filter styles, the price form with and without amounts, the stock box on and off, the offcanvas with and without chips (seen red with two classes changed); the wiring.
- `tests/list_search_controls_test.php` reads the price form's labels from its builder.
