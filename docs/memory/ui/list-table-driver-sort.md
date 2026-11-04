---
name: list-table-driver-sort
description: List table driver sorting and filters in the browser: heading clicks and Shift levels, level marks on the headings, the sort menu the driver redraws, browser-mode sort, filter buttons
---

## How does a heading sort?
- A heading button sorts (Shift adds a level, `sortAfterClick()`, the rule of `pg_list_sort_toggle()`); a server list reloads its first batch with `sort` (`pg_list_table_apply_sort()`, ORDER BY and keyset take every level).
- With 2+ levels each sorted heading shows its place (`bi-N-circle-fill`) and a later level its arrow (`sortMarks()`, Node test); the lead's arrow is the stylesheet's, on `aria-sort`, the only heading that carries it.

## Where is the sort menu?
- The screen's toolbar menu (`pg_list_sort_menu()`) carries `data-pg-list-sort-menu="<url basename>"` (or its 6th argument, for a list named apart from its screen, e.g. `view_regions_all_login_regions`) and `data-pg-sort-default`; the driver whose list has that name (`view_products`, `view_pages`, ...) redraws it from its levels on every sort: reverse, remove (never the last level), "Ardından şuna göre sırala" + Ekle, reset to the default; buttons, no reload (`sortEdit()`, Node test). Without JavaScript its links still work.
- A list with no such menu gets the same menu at the start of the bar's tools, without the reset.
- Why: the server drew that menu once, so after a heading click it showed and sent the old levels, and adding a level dropped the heading's sort; Shift was the only other way and no heading showed a second level. The owner's "birden fazla sıralama seçeneği çalışmıyor" (2026-11-04, #6466).

## How does a list printed whole sort?
- Browser mode (`data-pg-list-mode="browser"`) sorts on load by the kept levels; `sortRows()`/`compareSortValues()` (Node test): numbers as numbers, the rest by the page language's collator (`Intl.Collator`), empty first, ties kept, a cell's `data-pg-sort-value` before its text.
- A printed list (`data-pg-list-printed`) posts its sort to `list_table_sort` (`pg_list_table_printed_sort()`), so the screen comes back sorted the same way.

## How does a filter button narrow the list?
- Filters: a button `data-pg-list-filter="<list>" data-pg-filter-column="<key>" data-pg-filter-value="<value>"` ('' = all) is pressed in its group (`active`, `aria-pressed`) and calls `setFilter(key, value)`: a list printed whole keeps the rows whose cell has `data-pg-filter="<value>"`, a server list reloads with it in its params; a whole-list selection is replaced (Node test).

Related: docs/memory/ui/list-table-driver-browser.md, docs/memory/ui/list-table-driver.md
