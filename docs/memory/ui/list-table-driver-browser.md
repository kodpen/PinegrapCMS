---
name: list-table-driver-browser
description: List table driver in the browser (assets/js/pg_list_table.js): selection by id, Ctrl+A by condition, undo, rows on scroll, sort without reload, search box, texts, fill-height layout
---

## What does the browser part do?
- `assets/js/pg_list_table.js` (bundle `pg_list_table.min.js`, printed by the screen) starts every `.pg-list-table[data-pg-list-table]`; `root.pgListTable` is the driver, `PGListTable.init(scope)` starts injected markup.
- Selection by record id (`PGListTable.Selection`, pure, run in Node by `tests/list_table_js_test.php`): click alone, Ctrl/Cmd toggles, Shift range from the anchor, arrows and Space, Ctrl+A the whole condition (`as_of` now) where Ctrl+click makes exceptions. A click, × or Escape that replaces 2+ rows offers "Önceki seçimi geri al" for 10 s. A row's link, button or field keeps its own click. Double-click or Enter clicks `a[data-pg-open]`, else the row's first real link.
- The screen hears `pg-list-table:selection` on the root (`count`, `payload` for the resolver) and may put its bulk buttons in `.pg-list-table-actions`.
- Rows load by an IntersectionObserver on the foot and "Daha fazla göster"; a later request wins. A heading button sorts (Shift adds a level) by reloading the first batch; a list printed whole (`all`) sorts and searches in the browser (`Intl.Collator`, `pgListTurkishMatch()`). A search box is `<input data-pg-list-search="list name">`; new words drop a whole-list selection (with its undo).
- Texts come translated from `pg_list_table_texts()` (`data-pg-list-texts`, `%n` for the number). Rows carry no `aria-selected` (not allowed in role `table`): the bar's count is a live region.
- Layout (`backend.src.css`): the root fills the window from its top (`--pg-list-table-top`, set on resize), rows scroll in `.pg-list-table-scroll` under a sticky `thead`, the bar stays above; a hidden column hides its cells too.

Related: docs/memory/ui/list-table-driver.md
