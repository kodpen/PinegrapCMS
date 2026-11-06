---
name: list-table-quick-edit
description: Quick edit mode of the list table driver (assets/js/pg_list_table_edit.js): the source's edit key, F2, save on leave, refused values, undo, apply to the selected rows
---

## How does a list get quick edit?
- Every value the record's edit screen can change is a field; only computed values stay text (`list-table-quick-edit-values.md`).
- Its source names `edit` => `array('url' => the screen, 'columns' => the screen's pg_list_edit columns)` (`pg_list_table_edit_spec()`), only for a user who may change the list. Kept only while some column has `editable`; then `pg_list_table_html()` prints `data-pg-edit-url`/`data-pg-edit-columns` (`pg_list_edit_table()`) on the root and the "Hızlı düzenleme" button (`.pg-list-table-quick`, `aria-pressed`) in the bar's tools.
- A cell becomes a field when its column has `editable` **and** it carries `pg_list_edit_cell_attributes()` (`data-pg-edit` field, `-id`, `-value`) whose field is in the definitions. A row whose cell lacks the attributes (a product that does not track stock) stays text. The driver drops the cells' `tabindex`: the keyboard reaches a row, not every cell.
- The writes go to the screen's own `pg_list_edit_request()` endpoint, one cell per POST (`pg_list_edit`, `token`, `field`, `id`, `value`, and `was`: the value the cell opened with, for an undo the value the save left), unchanged; `pg_list_edit_request()` returns `was` (`pg_list_edit_was()`, null when not sent) for a screen that must refuse a stale overwrite (gift card balance, `list-table-quick-edit-gift-cards.md`); its answer (`value`, `html`, `class`, `cells`) draws the cell and the other cells it names (by `data-pg-cell`, `data-pg-col` or `data-pg-edit`); `rows` (id => cells) redraws other rows on screen the save changed too (`list-table-quick-edit-themes.md`).

## How does the mode behave?
- The button or F2 (in the list, or anywhere when the page has one such list; inside a field F2 too) turns it on and off. On: every such cell holds an input or a select; rows loaded later get theirs (`quickRows()` from `take()`). Off: the cells show what was stored; a field with focus is saved first.
- A field saves on `change` (leaving it), Enter (then the same column on the next row; Shift+Enter the row before) or Ctrl+S; Escape puts the stored value back. A `textarea` field keeps Enter for a new line; Ctrl+Enter goes on (`list-quick-edit-keyboard.md`). A field's keys never reach the rows (Space, arrows, Ctrl+A, Escape stay in the field).
- A refusal keeps the typed value in the field, marks it (`is-invalid`, `aria-invalid`) and puts the server's reason under it (`.pg-list-table-field-note`, `aria-describedby`) and in the live region. Nothing was saved.
- The details panel shows a field's cell as stored (`cell.pgQuickHtml`), not the input.

## What do undo and apply do?
- `QuickEdits` keeps the last save (one cell, or the group an apply wrote); "Son değişikliği geri al" writes the old values back once. A save that changed nothing keeps the one before.
- After a save on a selected row, "Seçili N satıra uygula" writes the same value to the other selected rows **on screen** whose cell is a field (`applyTargets()`), one request at a time. Under Ctrl+A only the loaded rows count; the button names the real number. That group is then the last save; its undo leaves the first row's own save in place. Refused rows are marked and counted in a toast.
- Pure parts in Node: `tests/list_table_quick_edit_js_test.php`.

## Why does the old cell editor skip these lists?
- `backend.src.js`'s click/F2 editor (`list-quick-edit-keyboard.md`) opens on any `td[data-pg-edit]`; inside `.pg-list-table` it now does nothing, so a click selects the row and the mode owns editing. DataTables screens keep the old editor until they move.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-driver-browser.md, docs/memory/ui/list-quick-edit-keyboard.md
