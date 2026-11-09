---
name: list-table-quick-edit-search
description: The list table quick edit's searching select (field type search with source): parse, the cell's stored name, the Combobox in the cell and its keys; the short link File cell
---

## How does a searching select work?
- Field type `search` with `source` (an address answering `?q=` with `{results: [{id, text}]}`, e.g. `pg_file_picker_source('file')`) and no options (#8424). `pg_list_edit_table()` passes `source`; `pg_list_edit_parse()` takes any id 1 to 2147483647 as a string ('' only when not required): the screen's own save checks the record. The cell carries its stored choice's name as `data-pg-edit-text` (`pg_list_edit_cell_attributes()`'s fifth argument); the answer's `text` renames it, so undo and apply show names.
- `openField()` builds a select of the empty and the stored choice (`searchSelect()`) and `pgFields.init()` draws it as a server-search Combobox (`pg-combobox-server-search.md`); the screen prints `pg_fields_script()`. Inside `.pg-list-table` the menu is placed fixed, as in a `.collapse`.
- Keys: on the closed toggle Enter (save, next row), Escape (stored choice back), F2 and Ctrl+S are taken in the capture phase before the toggle opens its menu; Space and the arrows open it; the menu keeps its own keys. A pick saves at once, moving through items saves nothing (`select-commit-on-pick.md`). No key reaches the rows. Focus moves to the toggle (`fieldTarget()`); `closeField()` destroys the Combobox. Used by the short link File cell.

Tests: `tests/list_edit_test.php` (`test_list_edit_search_reads_an_id`), `tests/list_table_quick_edit_js_test.php`, `tests/short_link_list_edit_test.php`.

Related: docs/memory/ui/list-table-quick-edit.md, docs/memory/ui/file-picker-search.md
