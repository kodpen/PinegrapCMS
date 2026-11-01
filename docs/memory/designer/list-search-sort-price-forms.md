---
name: list-search-sort-price-forms
description: List widget search, sort and price forms (server box, catalog's bound form): accessible names, translated words, no router page name in hidden inputs (#4457, #4538).
module: widgets
---

## Where are these forms built?
- The search box both list widgets print above their items is `_pg_sw_search_box_html($query_param, $query, $label, $width_cls, $page_param)` in `includes/fn/widgets.php`; `_render_system_widget_form_list()` and `_render_system_widget_catalog_listing()` call it. The catalog's sort and price forms stay inline in `widgets_catalog.php`.

## Which names do the controls carry?
- Search text box: `aria-label` = the search label (its placeholder is no label). The icon buttons: `aria-label` "Search" and "Clear search", icons `aria-hidden`.
- Sort: the label has `for` = the select's `id` (`pg-sw-sort-<n>`, a static counter so two listings on one page differ).
- Price: `pmin` / `pmax` have `aria-label` "Minimum price" / "Maximum price"; "Price", "Min", "Max", "Apply" go through `lang()` (they were fixed Turkish text before).

## Which query parameters do the forms carry?
- Hidden inputs come from `_pg_sw_hidden_query_inputs($skip)` and the Clear link from `_pg_sw_query_params()`: on a pretty address the router's `page` value is dropped, so a search no longer writes `/blog?page=blog&query=…`; on `/index.php?page=…` it stays.

## And the catalog's bound search form (`search_form` / `search_query` bindings)?
- Same rules since PR fixing #4538: `_render_system_widget_catalog_listing()` builds its hidden inputs with `_pg_sw_hidden_query_inputs(array($query_param, $page_param))` (before, a raw `$_GET` loop wrote `/magaza?page=magaza&query=…`), and `_apply_catalog_listing_bindings()` adds `aria-label` "Search products" to a bound box with no `aria-label` / `aria-labelledby` of its own (saved trees included).

## What keeps this from coming back?
- `tests/list_search_controls_test.php`, `tests/catalog_search_form_binding_test.php`.
