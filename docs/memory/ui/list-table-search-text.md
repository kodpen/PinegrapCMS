---
name: list-table-search-text
description: What the list table driver's browser search reads in a row (searchText()): icon marks as their label, never their ligature word; fields as their value, choice lists as their choice
---

## Which text of a row do the search words match?
- `searchText()` (`assets/js/pg_list_table.js`), called by `showHere()`: the row's text with each cell set apart by a space, where an icon font's element (`material-icons`, `material-symbols-*`) and any `role="img"` reads as its `aria-label` (or nothing), a `select` as its chosen option, a text `input` or `textarea` as its value; `script`, `style` and `template` are left out. Visually hidden text counts, as a screen reader reads it.

## Why not `row.textContent`?
- A check mark from `pg_list_table_check_cell()` is `<span class="material-icons" role="img" aria-label="Evet">task_alt</span>`: its text is the ligature word. On Comments, "alt" (an everyday Turkish word) and "task" kept every published row and "Evet" found none (#9030). In quick edit mode a choice list's text was every option it offers (every folder on Pages), and a name typed in a field was not text at all.
- Server lists search on the server; this is the browser mode only.

Tests: `tests/list_table_js_test.php` (`test_list_table_js_search_text`, run in Node).

Related: docs/memory/ui/list-table-driver-browser.md, docs/memory/ui/client-list-turkish-search.md
