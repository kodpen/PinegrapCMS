---
name: workflow-tables-lists
description: The list column of workflow tables (type list, PR #8486): texts or numbers one a line, contains, a count in formulas, a JSON list in flows, screen, import, privacy
---

## What is a list column?
- Column type `list` (`ws_wtable_column_types()`, label "List"; plan `plans/tables-relational.md`, step 7): several texts or several numbers in one cell, kept as a JSON array in the row's cells (no schema change).
- Option `of`: `text` (default) or `number` (`ws_wtable_list_kinds()`, `ws_wtable_list_of()`). It is fixed with the type: a change answers `type_fixed`; options sent without `of` keep the column's.

## What does a cell take?
- `ws_wtable_list_check()`: a list, its JSON text (`["a","b"]`), a text with one item a line, or one number. Items trimmed, empty ones dropped; no item left is an empty cell (null).
- At most `list_items` (100) items, a text item at most `list_item` (1000) characters (`ws_wtable_limits()`). Numbers in machine form, kept to six places, a whole one as an int.
- Codes: `not_list` (an object, a nested list, a bool), `list_too_long`, `item_too_long`, `not_number`, `out_of_range`; the screen words each (`ws_wtable_screen_error_text()`).

## How do conditions, sort and formulas read it?
- Comparisons: contains, not_contains ("does not contain", only for a list), empty, not_empty (`ws_wtable_where_type_operators('list')`). contains holds when one item is the value: a text without case (`ws_fold()`), a number by size. An empty list holds no contains and every not_contains. A list of numbers refuses a value that is no figure (`not_number`).
- Sorted by how many items a cell holds, empty cells last (`ws_wtable_sort_key()`). Not totalled.
- A formula reads a list as its item count (`ws_wtable_formula_operand()`): `Tags * 10`; an empty one is 0.

## How do flows see it?
- `ws_flow_row_cell_text()` gives a list cell as its JSON list (`["a","b"]`, `[1,2.5]`), in the row start box, Read a row, and each item of Read rows' `rows`. A For each box takes it: the canvas marks the value `list` (`rowCellValues()`) and `listValue()` accepts it.
- Writing a list from Add a row / Change a row: the canvas field takes one item a line (numbers the site's way, kept as a list) or one value such as `{{trigger.cell_4}}`, which the store reads as a JSON list.

## Screen, download, import, privacy
- Table screen: row form a textarea, one item a line ("One item a line."), numbers the site's way; the list joins items with ", ". New column form: "Items" Texts / Numbers, shown disabled on a made column.
- Excel download (`ws_wtable_export_cell()`): one item a line in the cell; the import reads it back the same way.
- Import: `list` is among the types a person may choose (`ws_wtable_import_types()`), never guessed. A new list column is numbers when every item reads as a number. `ws_wtable_import_cell()` splits lines, numbers read as a number column's.
- Privacy (`pg_privacy_wtable_cell_hits()`): a list cell names nobody, its column there or deleted, so a number item is never taken for an account id.

## Tests
- `tests/workspace_wtables_test.php` (options, cells), `workspace_wtable_where_test.php` (lists), `workspace_wtable_rows_test.php` (formula), `workspace_wtable_screen_test.php`, `workspace_wtable_import_test.php`, `workspace_wtable_query_test.php`, `workspace_flow_row_events_test.php`, `privacy_erase_test.php`.
