---
name: list-table-driver-browser
description: List table driver in the browser (assets/js/pg_list_table.js): selection, Ctrl+A, undo, rows on scroll, sort, search, columns menu, details panel, phone cards, thumbnails, layout
---

## What does the browser part do?
- `assets/js/pg_list_table.js` (bundle `pg_list_table.min.js`, printed by the screen) starts every `.pg-list-table[data-pg-list-table]`; `root.pgListTable` is the driver, `PGListTable.init(scope)` starts injected markup.
- Selection by record id (`PGListTable.Selection`, pure, run in Node by `tests/list_table_js_test.php`): click alone, Ctrl/Cmd toggles, Shift range from the anchor, arrows and Space, Ctrl+A the whole condition (`as_of` now) where Ctrl+click makes exceptions. A click, × or Escape that replaces 2+ rows offers "Önceki seçimi geri al" for 10 s. A row's link, button or field keeps its own click. Double-click or Enter clicks `a[data-pg-open]`, else the row's first real link.
- The screen hears `pg-list-table:selection` on the root (`count`, `payload` for the resolver) and may put its bulk buttons in `.pg-list-table-actions`.
- Rows load by an IntersectionObserver on the foot and "Daha fazla göster"; a later request wins. A heading button sorts (Shift adds a level) by reloading the first batch; a list printed whole (`all`) sorts and searches in the browser (`Intl.Collator`, `pgListTurkishMatch()`). A search box is `<input data-pg-list-search="list name">`; new words drop a whole-list selection (with its undo).
- Texts come translated from `pg_list_table_texts()` (`data-pg-list-texts`, `%n` for the number). Rows carry no `aria-selected` (not allowed in role `table`): the bar's count is a live region.
- The bar always shows (`is-empty` hides its count and bulk buttons): its right end holds the columns menu (show, hide, up, down, Reset; a Bootstrap `.menu` with `data-bs-auto-close="outside"`) and the "Ayrıntılar" switch. Preferences are `{order, hidden, details}` in localStorage `pg.list.<user id>.<list>` (`data-pg-list-user`), read through the pure `columnsView()`/`columnsToggle()`/`columnsMove()`/`columnsReset()` (Node test): the first column always leads and never hides, a non-hideable column never hides, a key the list lost is dropped, a new column follows its model neighbour. Storage that throws leaves the choice for the page only.
- `applyColumns()` reorders the heading and every row's cells by `data-pg-col` and sets `hidden`; rows loaded later are built in that order.
- Details panel (`aside.pg-list-table-details`): every column of the current row (the last clicked, or the row the keyboard is on), hidden ones too, in the user's order, from the cells' own markup. Beside the rows (20rem) from 1024 px, a fixed side sheet below.
- Below 768 px each row is a card: the heading is visually hidden and each shown cell is preceded by its `data-pg-label` (set on every cell). A cell's `text-center`/`text-end` utility still aligns its value.
- Thumbnails: an `img[data-pg-thumb="url"]` in a cell gets its `src` when it comes into sight, at most 6 loading at once; the screen builds the url with `pg_image_panel_thumb_url()`.
- Layout (`backend.src.css`): the root fills the window from its top (`--pg-list-table-top`, set on resize), rows scroll in `.pg-list-table-scroll` under a sticky `thead` (the grid has no `.table` 3rem top margin), the bar stays above; a hidden column hides its cells too.

Related: docs/memory/ui/list-table-driver.md
