---
name: form-list-result-count
description: form_list_view ^^__result_count^^ (records matched, capped at max_results, grouped digits), the flags has_records / no_records, the count strip (empty loop area), canvas and installer preview
---

## What does `^^__result_count^^` count?
- The complete records the list matched: its filters, the visitor's search and the viewer filter, less its `offset` ([[widget-list-paging]]). Capped at `max_results` (`min(total − offset, max_results)` when set): the number a visitor can page through, as the classic list's "Found N results" (`get_form_list_view.php`, `form_list_view_system.php`) and search_results count it.
- Printed in the surround and in every row, grouped with the site's separators (`h(pg_format_number($n, 0))`, "1.284"). Escaped once and passed as a finished value, so it is not in `pg_sw_raw_token_keys()`.
- 0 when nothing matches, when a filter closes the list (`AND (0 = 1)`, `form-list-filters-fail-closed.md`) and in the empty state.
- Editor: Bind Data "Number of results" (search_results' label for the same name). The canvas shows 12 (`_SD_NUMERIC_TOKENS`).

## Why this name, and does search print it the same way?
- One value has one name (`token-dictionary.md`); search_results already calls its number of results `__result_count`.
- The format is the same: search_results groups its number in the page's language too (`pg_format_number($total, 0)`, as its heading does; owner, 2026-10-27, W12). Before, it printed plain digits ("1284").

## Which flags does a list set?
- `has_records` (count > 0) and `no_records` (count = 0), in the surround and in the rows (a row always has `has_records` on). The list marks only its own flags (`_pg_sw_mark_visibility()` list).
- Not search's `has_results` / `no_results`: `_pg_tpl_preview_flag_on()` is keyed by flag name across widget types. The installer preview draws `has_records` parts and hides `no_records` parts; turning `has_results` on there would show search's found-results parts in every template.

## What does a list with an empty loop area do?
- Loop area empty, surround not (a "1,284 topics" strip): it runs the COUNT, plus the exact-value probe when an address value applies, and registers its head state (`pg_sw_url_filter_state()`, multi-list rule). It then returns the surround with the count, the flags and the search box: no rows query, no pagination. The empty message shows only when nothing matched.
- Before, such a list printed the empty message, ran no query and touched no head state.
- `$loop_empty` is taken before "Show only when" is resolved on the template, so a loop area that only a flag empties stays a list with rows and pages.
- Neither loop nor surround: '' without a query.

## What does it cost?
- Nothing new on a list with rows: the count is the pagination COUNT it already runs. A strip runs that COUNT alone.

## How to show "12 unanswered" beside a 5-row box?
- Two lists. The box with max_results 5 counts 5. Next to it a count strip without max_results, filter "Number of Comments is less than 1" (`form-list-filters-fail-closed.md`), prints the full 12.

## How is it tested?
- `tests/form_list_result_count_test.php`: source order in `_render_system_widget_form_list()` (empty loop taken before visibility, cap before pagination, strip returns after the head state), the token and flag maps, the editor lists and the installer preview flags. No database run.
