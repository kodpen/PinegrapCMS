---
name: layout-type-converter-search-results
description: A search results page's layout file into the Search Results widget (LE10d, #11764): the three lists as places of one list, row and layout flags, the search box, starting sites
module: layout_convert
---

## What does the search results map do?
- `_pg_lc_search_results_map()` in `includes/fn/layout_convert.php` via `pg_layout_piece_map('search results')` ([[layout-type-converter]]). No type row is read: the page's search settings are not in the layout.
- The featured, catalog and other results loops (`$featured_items`, `$catalog_items`, `$results`) are `list:<row flag>` loops under `lists`: each stays a place of the widget's one list, under its own heading, its body under `result_is_featured`, `result_is_catalog` or `result_is_page`. Under `lists` the loops are not merged and none is dropped.
- `<form <?=$attributes?>>` is bound to `search_form`; the `<page id>_query` box to `search_query` (the widget names it `query`); `$system` and `$messages` are the widget's.

## What does each piece become?
- `h($query)` `__search_query`; `number_format($number_of_results)` `__result_count`; the stock template's `lang()` sentences `__results_heading`.
- Row: `url` `__result_url`, `title` and a catalog row's `short_description` `__result_title` (a product without one shows its name), `full_url` `__result_full_url`, `description` `__result_excerpt`, `image_url` `__result_image`, `price_info` `__result_price`.
- Flags: `$query == ''` `no_query`, `$number_of_results == 0` `no_results` (else `has_results`), `$limited` `is_limited` (else `not_limited`), "result<s>" `many_results` / `one_result` (the line drawn once for each), `$featured_items` `has_featured_results`, `$catalog_items` `has_catalog_results`, `$results` `has_page_results`, `$featured_items or $catalog_items` `has_featured_or_catalog_results`.
- Row flags: `result_has_excerpt`, `result_has_image`, `result_has_price`. `$item['url']` and `$item['short_description']` are kept (every row has both).

## What did the widget gain?
- `_render_system_widget_search_results()` (`includes/fn/widgets.php`): each row carries `group` (featured, catalog, pages). `pg_sr_layout_flags()` gives the layout flags; the kinds' flags read the rows of the page shown, so page 2 of a long search shows only its own headings.
- Every loop area after the first is taken out with `pg_cart_extra_loops()` and filled row by row; `pg_sr_row_template()` cuts a node under a row flag (`pg_sr_row_flags()`) the row does not hold. A starter tree with one loop draws as before.
- The flags are in the editor's "Show only when" list for `search_results`.

## Starting sites and tests?
- Page 216 (both sites): converts with nothing dropped, so it converts in the move run; the stock template too. Before this step a search results file page was always a System region.
- `tests/search_results_layout_test.php`.

Related: docs/memory/designer/layout-type-overview.md, docs/memory/designer/layout-type-converter-photo-gallery.md
