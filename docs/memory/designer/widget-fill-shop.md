---
name: widget-fill-shop
description: A bound value's format in the shop widgets (catalog, catalog detail, cart, express order, order view): fills, no change without a format, order view dates, fallback words (#1701).
---

## Which fills read a format in the shop kinds?
- Catalog, catalog detail, cart and order view: `pg_sw_fill_longest()` (`includes/fn/widgets_fill.php`), the longest-first `str_replace()` loop they always had.
- The catalog listing's page tokens (breadcrumb, group tree, filters): `_pg_catalog_fill_page_tokens()`, in the map's order as before.
- Express order (items, totals, recipients): `_eo_replace_tokens()` → `pg_sw_fill_token_map()`, `strtr()` over whole-token keys (`'^^__cart_total^^'`).

## Why does a tree without a format render byte for byte?
- Each fill takes the typed pass (`pg_sw_fill_typed()`) only when its template holds `^^%%`; otherwise the old loop runs unchanged. `tests/widget_fill_shop_test.php` compares them with the old loops, a value holding a token included (the old loops rescan it; the typed pass never does).

## What happens to a token a later pass fills?
- The typed pass leaves a token not in its map with its suffix: the listing's page pass or the express order's static pass reads it there; the sweep (`pg_sw_sweep_tokens()`) takes an unanswered one with its suffix.

## Which shop dates take a date format?
- `_pg_sw_order_view_dates()`: `__order_date` a `date and time` (the order's unix time stamp), `__arrival_date` and `__withdrawal_deadline` dates (`Y-m-d`). A missing or `0000-00-00` date keeps the configured format. The editor's `SW_DATE_FIELD_TOKENS` lists the three, so the order view draws a Date format row for them.

## What is still open?
- Catalog detail's variant switch (`pg_civ_variants.js`) writes the chosen variant's raw value into each `data-pg-bind` element, so a formatted value shows unformatted after a switch: there is no JS twin of the filters.

## How is an empty value given fallback words?
- Not by a format or a default-text setting: the bound element shows with `show_if not_empty`, a sibling with `show_if empty` on the same token holds the words, which the site translation reads like any node text ([[widget-conditions]]).
