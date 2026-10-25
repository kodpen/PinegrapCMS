---
name: form-list-filters-fail-closed
description: A form list filter on a missing field or unknown operator shows no record (page, feeds, sitemap, index); address filters unchanged; editor warning; unanswered lists use comments less than 1
---

## What happens when a list filter cannot be applied?
- A `form_list_view` filter naming a field the form no longer has (renamed or deleted, the widget pointed at another form) or an operator outside the English constants (a hand-written "eşittir") closes the list: no record, its empty state.
- Only 50 filters are applied: a 51st row with a field counts in `invalid` and closes too (it was silently dropped). A list's detail-page records go through the record view's filters first, then the list's, so 49 view filters plus 2 list filters close the feed, sitemap and index.
- Before, the row was skipped and the others applied: renaming the `status` field of a list filtered "Status is equal to Published" listed the drafts on the page, in `?rss=true`, the sitemap and the search index.

## Where is it decided?
- One pure helper, `pg_sw_filter_sql_closed($filter_sql, $record_view = false)`, over the counts of `pg_sw_form_list_filter_sql()`: `missing` or `invalid` > 0 closes; `$record_view` also closes on `address` > 0.
- `_render_system_widget_form_list()` asks it and sets the WHERE to `AND (0 = 1)`: the page, the canvas preview and the list page's own search item.
- `pg_sw_record_filter_clause()` asks it in both modes (`$strict` = record view), so `pg_sw_list_record_filter_clause()` closes for the list's feed and per-value feed (`pg_sw_form_feed_parts()`), the sitemap (`get_sitemap_info.php`) and the search index's record items (`update_search_index.php`).
- Only these two functions call `pg_sw_form_list_filter_sql()`; a test fails on a third.

## What does not close a list?
- An address filter (`url parameter`, `form-list-url-filters.md`) with no value in the address: left out, every record listed. With no address at all (`$request` null: sitemap, index) it counts in `address`, which closes only a record view.
- An address filter whose field is gone closes the list only when the address carries a value (the field is looked up after the value); an unknown operator closes it always (checked first).
- A blank row (no field) counts nowhere.

## What does the editor show?
- `_swFormListFiltersHtml()` (lists, record views, recent comments lists): besides the "(incompatible — choose again)" option, a row whose field is not in the form (once the fields are loaded) or whose operator is unknown gets an amber line saying no record is shown and what to choose again.

## What is unchanged?
- Classic list views (`get_form_list_view.php`); no schema change.

## Which filter lists unanswered records?
- "Number of Comments is less than 1". A numeric operand is not quoted (`prepare_sql_operation()`), so `IFNULL(x, '') < 1` compares numbers, '' reads as 0, and a record with no `submitted_form_info` row on the detail page (never viewed, never commented) is found. Read from the code; not yet run on a database.
- "is equal to 0" misses that record: `IFNULL(x, '') = '0'` compares strings ('' is not '0'), and "is not equal to 0" lists it. The classic Form List View builds the same SQL.
- Owner decision 2026-10-23: the classic layer stays the yardstick, so no widget-only `IFNULL(counter, 0)`; templates use "less than 1".
- Without a detail page there is never a row, so a "less than 1" list shows every record.

## Where are the tests?
- `tests/form_list_filter_closed_test.php` (decision, invalid operator, the 50-row cap, address rows, every path, editor) and `tests/record_view_filters_test.php`.
