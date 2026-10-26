---
name: widget-list-paging
description: Form list widget paging (W12a, #1924): own page parameter (page_param), offset, paging tokens and flags, canonical of a later page; what W12 still leaves for its next slices
---

## Which page parameter does a form list read?
- `pg_sw_list_page_param($cfg)`: the list's `page_param` setting when `pg_sw_url_param_name()` accepts it (a letter first, `[a-z0-9_]`, at most 32, not reserved, no `utm_`/`pg_`), else `page_number`. Empty is the default: running sites' `?page_number=` addresses keep working, and every list without a name still shares that one page number.
- Two lists with names of their own (`topics_page`, `news_page`) turn their pages apart. A list never reads `page_number` as a fallback once it has a name (the classic list does read `<page_id>_page_number` then `page_number`): independence is the point.
- The search text (`query`) stays page-wide. The search box drops the list's own parameter when it submits, the clear link too.
- The editor (List Settings, form list only): "Page number in the address", generic `data-sw-cfg-kind="param"` normalised by `_swUrlParamName()`. The name is not reserved: another list's URL filter could read the same name; the designer picks names that differ.

## What does `offset` do?
- `pg_sw_list_offset($cfg)`: 0 to 100000 matched records skipped before the first row (a box shows the newest, the list beside it starts at the second). Editor: "Skip the first records" (`_swListOffset()` twin).
- `__result_count` = (COUNT − offset), then capped by `max_results`; pages run through that number; SQL `OFFSET` = offset + (page − 1) × per page. The empty message shows when nothing is left after the offset. `has_filter_value`, the head's `empty` state and the feed read the records the filter found, not the offset (the feed lists the form's newest 50 anyway).

## Which tokens and flags describe the pages?
- `pg_sw_list_paging($param, $current, $pages)`: `__page_number` (page shown), `__page_count` (search's name for the same value), `__prev_page_url`, `__next_page_url` (`?…` with the other query values kept, escaped once, '' at the ends); flags `has_prev_page`, `has_next_page`, `has_more_pages` (the comments widget's name for more than one page). In the surround and in every row.
- `pg_sw_list_current_page()`: the address value clamped to 1..pages. A count strip (empty loop area) has one page at most, so its flags stay off.
- The template installer's preview turns them off (`_pg_tpl_preview_flag_on()`).

## What does the canonical of a later page carry?
- `pg_sw_url_filter_page(..., $page_param)` reads the list's own name; `pg_sw_url_filter_state()` stores `page_param` beside `page_number` only when it is not `page_number` (the blank state is unchanged); `pg_sw_url_filter_query()` writes `&<name>=N`.

## What is left of W12?
- W12b done: the page links as a list ([[widget-lists-paging]]).
- W12c done (#1968): the catalog listing and search results ([[widget-list-paging-catalog-search]]). W12d: their page links list ([[widget-lists-paging]]).
- Search groups (digit grouping of search's `__result_count`) waits for the owner ([[form-list-result-count]]).

## How is it tested?
- `tests/widget_list_paging_test.php` (helpers, tokens and flags, canonical, renderer wiring, editor), `tests/form_list_result_count_test.php` (count after the offset). No database run.
