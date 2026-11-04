---
name: list-table-driver-browser
description: List table driver in the browser (assets/js/pg_list_table.js): selection, Ctrl+A, undo, rows on scroll, sort, search, columns menu, details panel, phone cards, thumbnails, layout
---

## What does the browser part do?
- `assets/js/pg_list_table.js` (bundle `pg_list_table.min.js`) starts every `.pg-list-table[data-pg-list-table]`; `root.pgListTable` is the driver, `PGListTable.init(scope)` starts injected markup.
- Selection by record id (`PGListTable.Selection`, pure, Node test `tests/list_table_js_test.php`): click, Ctrl/Cmd toggles, Shift range from the anchor, arrows and Space, Ctrl+A the whole condition (`as_of` now) with Ctrl+click exceptions. Replacing 2+ selected rows offers "Önceki seçimi geri al" for 10 s. A row's link, button or field keeps its click; double-click or Enter clicks `a[data-pg-open]`, else the first real link.
- The screen hears `pg-list-table:selection` on the root (`count`, resolver `payload`); its bulk buttons may go in `.pg-list-table-actions`.
- Rows load by an IntersectionObserver on the foot and "Daha fazla göster"; a later request wins. A heading button sorts (Shift adds a level) by reloading the first batch; a list printed whole (`all`) sorts and searches in the browser (`Intl.Collator`, `pgListTurkishMatch()`). Search box: `<input data-pg-list-search="list name">`; new words drop a whole-list selection (undo kept).
- Browser mode (`data-pg-list-mode="browser"`): sorts on load by the kept levels; `sortRows()`/`compareSortValues()` (Node test): numbers as numbers, the rest by the page language's collator, empty first, ties kept, a cell's `data-pg-sort-value` before its text. Ctrl+A selects the shown rows by id (`Selection.selectIds()`, payload `{ids}`); Shift ranges, arrows and the empty line skip hidden rows. A printed list (`data-pg-list-printed`) posts its sort to `list_table_sort`.
- Filters: a button `data-pg-list-filter="<list>" data-pg-filter-column="<key>" data-pg-filter-value="<value>"` ('' = all) is pressed in its group (`active`, `aria-pressed`) and calls `setFilter(key, value)`: a list printed whole keeps the rows whose cell has `data-pg-filter="<value>"`, a server list reloads with it in its params; a whole-list selection is replaced (Node test).
- Texts: `pg_list_table_texts()` (`data-pg-list-texts`, `%n` the number). No `aria-selected` on rows (not allowed in role `table`): the bar's count is a live region.
- The bar always shows (`is-empty` hides its count and bulk buttons): its right end holds the columns menu (show, hide, up, down, Reset; `.menu`, `data-bs-auto-close="outside"`) and the "Ayrıntılar" switch. Preferences are `{order, hidden, details}` in localStorage `pg.list.<user id>.<list>` (`data-pg-list-user`) through the pure `columnsView()`/`columnsToggle()`/`columnsMove()`/`columnsReset()` (Node test): the first column leads and never hides, nor does a non-hideable one; a lost key is dropped, a new column follows its model neighbour. Storage that throws: the choice holds for the page.
- `applyColumns()` reorders the heading and every row's cells by `data-pg-col` and sets `hidden`; rows loaded later are built in that order.
- Details panel (`aside.pg-list-table-details`): every column of the current row (last clicked, or the keyboard's), hidden ones too, in the user's order, from the cells' markup. Beside the rows (20rem) from 1024 px, a fixed side sheet below.
- Below 768 px each row is a card: the heading is visually hidden, each shown cell led by its `data-pg-label` (set on every cell); a cell's `text-center`/`text-end` still aligns it.
- Thumbnails: an `img[data-pg-thumb="url"]` in a cell gets its `src` once in sight, at most 6 loading at once; the url comes from `pg_image_panel_thumb_url()`.
- Layout (`backend.src.css`): the root fills the window from its top (`--pg-list-table-top`, set on resize), rows scroll in `.pg-list-table-scroll` under a sticky `thead` (no `.table` 3rem top margin), the bar stays above; a hidden column hides its cells.

Related: docs/memory/ui/list-table-driver.md
