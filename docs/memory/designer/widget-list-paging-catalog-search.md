---
name: widget-list-paging-catalog-search
description: Catalog listing and search results paging (W12c): own page parameter, offset, paging tokens and flags as the form list has them; what differs from the form list, the canonical, what is left
---

## What did the two widgets get?
- The form list's settings and values ([[widget-list-paging]]): `page_param` (`pg_sw_list_page_param()`; empty = `page_number`, so running sites' `?page_number=` addresses keep working), `offset` (`pg_sw_list_offset()`), tokens `__page_number`, `__page_count`, `__prev_page_url`, `__next_page_url` and flags `has_prev_page`, `has_next_page`, `has_more_pages` (`pg_sw_list_paging()`).
- Editor: the catalog listing shares the form list's "List Settings" rows (`_isFormListView || _isCatalogListing`); search results has "Skip the first results" and "Page number in the address" in its own section. Tokens in `SW_CATALOG_TOKEN_GROUPS` (group "Pages") and `SW_SEARCH_RESULTS_TOKEN_GROUPS`; flags in both kinds' "Show only when" lists.

## How does the catalog listing apply them?
- The COUNT less the offset, then the `max_results` cap; SQL `OFFSET` = offset + (page − 1) × per page. Nothing left after the offset: the empty message (`$no_products`).
- The search box, filters, chips and sort links already dropped `$page_param`; they now drop the listing's own name.
- Tokens: added after `pg_sw_escape_token_values()` (escaped once) to `$page_tokens`, which fill the surround and the cards.
- Flags: the surround only. Its static tree is marked (`_pg_sw_mark_visibility(..., paging flags)`) before render and resolved at the end, before the tokens. A card's flags are resolved by `_pg_catalog_card_templates()` (rating only), which cuts any other marked span, so paging flags are not marked inside a card: an element there carrying one is always drawn.

## How do the search results apply them?
- `offset` slices the collected results before counting: `__result_count`, the heading's number, `has_results` / `no_results` and the pages all read what is left. `__result_number` counts from 1 after the offset.
- The flags join `_eo_apply_visibility_bindings()` (known before the render); tokens go into the static values and every row. `__page_count` keeps its old value (pages, at least 1).
- The search box's hidden inputs drop the widget's own page name.

## What about the canonical?
- Unchanged for both: neither has URL filters, so their later pages keep the bare canonical, as before. The W12a canonical rule (`page_param` in the URL-filter state) applies only to a form list that applied an address value.

## What is left?
- The page links as a designable list (W12b's `paging.pages`) is still offered only in the form list (`pg_sw_kind_lists()`).
- Search groups (digit grouping of search's `__result_count`) waits for the owner ([[form-list-result-count]]).

## How is it tested?
- `tests/widget_list_paging_test.php`: `test_catalog_listing_paging_wiring`, `test_search_results_paging_wiring`, `test_catalog_and_search_paging_editor` (source order and wiring, panels, palettes, flag lists). No database or browser run.
