---
name: list-search-sort-price-forms
description: List widget search, sort and price forms (server box, catalog's bound form) and page links: accessible names, translated words, no router page name in hidden inputs or links (#4457, #4538).
module: widgets
---

## Where are these forms built?
- The search box both list widgets print above their items is `_pg_sw_search_box_html($query_param, $query, $label, $width_cls, $page_param)` in `includes/fn/widgets.php`; `_render_system_widget_form_list()` and `_render_system_widget_catalog_listing()` call it. The catalog's sort and price forms stay inline in `widgets_catalog.php`.

## Which names do the controls carry?
- Search text box: `aria-label` = the search label (its placeholder is no label). The icon buttons: `aria-label` "Search" and "Clear search", icons `aria-hidden`.
- Sort: the label has `for` = the select's `id` (`pg-sw-sort-<n>`, a static counter so two listings on one page differ).
- Price: `pmin` / `pmax` have `aria-label` "Minimum price" / "Maximum price"; "Price", "Min", "Max", "Apply" go through `lang()` (they were fixed Turkish text before).

## Which query parameters do the forms carry?
- Hidden inputs come from `_pg_sw_hidden_query_inputs($skip)` and the Clear link from `_pg_sw_query_params()`: on a pretty address the router's `page` value is dropped, so a search no longer writes `/blog?page=blog&query=…`; on `/index.php?page=…` and on the site's root (`/?page=…`, the router hands that query on as it came) it stays.

## And the page links and the catalog's filter links?
- `_pg_sw_build_query()` and `_build_clear_filters_query()` (`includes/fn/designer.php`) start from `_pg_sw_link_params()`, i.e. `_pg_sw_query_params()`: pagination (`pg_sw_pagination_html()`, `pg_sw_list_paging()`, the paging list rows), the catalog's sort, stock, attribute and chip links. Before (bug hunt), every page link on a pretty address read `/blog?page=blog&page_number=2` while the page's canonical is `/blog?page_number=2`.

## And the catalog's bound search form (`search_form` / `search_query` bindings)?
- Same rules since PR fixing #4538: `_render_system_widget_catalog_listing()` builds its hidden inputs with `_pg_sw_hidden_query_inputs(array($query_param, $page_param))` (before, a raw `$_GET` loop wrote `/magaza?page=magaza&query=…`), and `_apply_catalog_listing_bindings()` adds `aria-label` "Search products" to a bound box with no `aria-label` / `aria-labelledby` of its own (saved trees included).

## What keeps this from coming back?
- `tests/list_search_controls_test.php` (forms, page links, `/index.php` and `/` keep `page`), `tests/catalog_search_form_binding_test.php`.

## How does the catalog listing match the search text?
- The product search (name, descriptions, details, keywords, slug, attribute option labels) and the child group search (name, short description, slug) match through `pg_catalog_listing_search_sql()` (`pg_list_search_where()`'s LIKE on `pg_list_like_pattern()`, I/İ/ı/i folded, values written in with `e()`): a typed `%` or `_` is a plain character (#7607).
- Before #5155 the pattern was a raw `'%term%'`, so a search for `_` or `%` listed every product (`tests/catalog_search_like_test.php`).

## How does the catalog listing read the search text?
- `?query=` only when `is_scalar()` (an array is no search), trimmed, cut at 100 characters with `mb_substr()`. Before the fix it was cut at 100 bytes: a long Turkish query split a letter, `h()` printed the invalid UTF-8 as nothing and the box came back empty (`tests/catalog_search_query_read_test.php`).
