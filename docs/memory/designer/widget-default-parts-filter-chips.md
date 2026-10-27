---
name: widget-default-parts-filter-chips
description: The catalog listing's filter chip parts (W16, #2218): the catalog.active_filters list, the six placements replaced, the clear button, the pushed chip row by pg_sw_placed()
---

## What do the parts draw?
- `pg_sw_part_catalog_active_filters()` (`includes/fn/widgets_parts.php`): `div.pg-sw-attr-active-filters.mb-3`, shown by `__has_active_attr_filters eq 1`, holding a `catalog.active_filters` list of `a.badge.text-bg-secondary…` (href `__filter_chip_url`, text `__filter_chip_label` before its `span.bi.bi-x-lg.ms-2`, `textPosition: start`).
- `pg_sw_part_catalog_filter_chips_inner()`: that box, then `span.ms-auto` (`__has_active_filters eq 1`) > `a.btn.btn-sm.btn-outline-danger` (href `__clear_filters_url`) with `i.bi.bi-x-circle.me-1` and "Clear Filters" (translated when the part is made).
- `pg_sw_part_catalog_filter_chips()`: `div.pg-sw-filter-chips.d-flex.align-items-center.gap-2.flex-wrap.py-2` (`__has_active_filters eq 1`) around the inner part.
- Same words, links and classes in order as the old markup (owner option B). The badges' inline `font-weight:normal` and the cross's `font-size:.7em` moved to `pg_carousel_layout.css` (v7), with a flex row on the box so the white space between badge nodes adds no gap.

## Where do the rows come from?
- Source `catalog.active_filters` (`pg_sw_list_active_filter_sources()`, `widgets_lists_product.php`), family `filter_chips`, listing only, key `__has_active_attr_filters` (`1`/`0`), rows under key 1, tokens `__filter_chip_label` ("Colour: Red"), `__filter_chip_url` (the listing without that filter). Pure builder `pg_sw_list_active_filter_rows()`; the renderer's chip loop reads the same rows.
- `__clear_filters_url` is the clear button's old address unescaped (`$clear_url_raw`); the fill escapes it once.
- The list stands anywhere in a listing (`_sdListNeedsMainLoop()`).

## Which placements become the parts?
- `__filter_chips` bound or a text that is the token alone: host + full part. `__filter_chips_inner_html` and `section:filter_chips`: the inner part. `__active_filters_html`, `__active_filters_inner_html` (the old inner held the box too) and `section:active_filters`: the attribute box.
- The four tokens are no longer filled, raw or offered; the two section options and their canvas labels are gone; `__filter_chips` left `pg_sw_cond_denied_tokens()`. The listing starter's chip div holds the inner part.
- The offcanvas still prints its own chips as markup (it holds form controls: parts with controls wait on the owner).

## What changed for running sites?
- The pushed all-filters chip row (`pg-sw-active-filters`: category, search, price, stock, sort and attribute chips with "Clear all") is left out when the tree places the chips: `pg_sw_placed($tree, ['catalog.active_filters', the old tokens])`. Before, only the two section bindings left it out; a layout that wrote `^^__filter_chips^^` or `^^__active_filters_html^^` in a text showed both rows and now shows the parts only.
- A token inside a longer text prints empty, as for the stars and crumbs.
- The pushed row itself is still server-built: it sits before the pushed toolbar, which holds controls; take both together.

## Tests?
- `tests/widget_default_parts_filter_chips_test.php`: rows; each placement old vs new by words, classes and links in three states (attribute filters, a price filter only, none); escaping; no inline style; placement and retired tokens; the editor twin under Node.
