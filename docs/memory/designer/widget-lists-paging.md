---
name: widget-lists-paging
description: Page links as a designable list (W12b #1947, W12d #1984 catalog and search): source paging.pages, key __page_number, rows, tokens, built-in pagination left out, starter, placement
module: widgets_lists_paging
---

## What does a designer build the page links from?
- A list (`loop_area`, `props.source = 'paging.pages'`, family `paging`, `includes/fn/widgets_lists_paging.php`, engine [[widget-lists]]), offered in the form list, the catalog listing and the search results (`pg_sw_kind_lists()`; JS `kinds` of `SW_LIST_SOURCES['paging.pages']`).
- One row per link, as `pg_sw_pagination_html()` draws them: the first page, a gap, two pages either side of the page shown, a gap, the last page. Previous and next are not rows: the widget's `__prev_page_url` / `__next_page_url` with `has_prev_page` / `has_next_page` ([[widget-list-paging]]).
- No rows for a single page (the built-in pagination prints nothing then); an empty list is a cut point.

## How does the list find its rows?
- Key `__page_number`, the page shown, which the form list fills in its surround and in every record: rows are published as `array('paging.pages' => array(page => rows))` (`pg_sw_list_paging_rows()`), so the list draws wherever it stands, outside the records too. No extra static token.
- The renderer publishes only when `pg_sw_list_wanted('paging')` (the design drew such a list), and then leaves its built-in pagination out. Without the list the output is as before.
- `pg_sw_list_wanted()` is true only after the tree is drawn: a list registers its template during `_render_tree_node()`. The search results therefore put `<!--pg-sr-pagination-->` in the `pagination` section, ask after the render, and replace it with the pagination or ''. One page: the section is still left out, as before. The catalog listing builds its pagination after its render anyway.
- Catalog listing: the key and the previous / next items work in the surround (paging flags are resolved there only, [[widget-list-paging-catalog-search]]); a list inside a card repeats per card.

## Which tokens does a row carry?
- `__pager_number` (empty for a gap), `__pager_url` (`?…` with the other query values kept; empty for a gap and for the page shown, which the built-in pagination does not link either), `__pager_is_current`, `__pager_is_gap` ('1' or empty). Raw, escaped once by the list pass. `__page_link_<n>` is taken by page links, hence `__pager_`.
- A link, the current page and a gap are told apart by conditions (`show_if`) on the row, never a class.

## What does the editor offer?
- Palette "Lists in a record": Page links. Starter (`_sdPagingListStarter()`): a Bootstrap `nav` / `ul.pagination`, a previous item on `has_prev_page`, the list with three `li` (a link on `__pager_url` not empty, an active span on `__pager_is_current`, a disabled gap on `__pager_is_gap`), a next item on `has_next_page`.
- It lands anywhere in a form list (`_sdListNeedsMainLoop(kind, source)` is false for it). Sample rows on the canvas: current, link, gap, link.

## How is it tested?
- `tests/widget_lists_paging_test.php`: source and kinds, rows against the built-in pagination's numbers, a list in a surround drawn by the finish pass (escaped once, no marker left), the same in a catalog surround with its paging flags, the three renderers' wiring, the editor. No database or browser run.
