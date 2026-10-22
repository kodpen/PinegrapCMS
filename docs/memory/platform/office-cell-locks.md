---
name: office-cell-locks
description: Which workbook cells the file window never changes (formulas, merges, tables, signed or password-reserved files) and how the grid tells which cells of a protected sheet are open
---

## What is never changed?
- Formula cells, `vm` cells (IMAGE(), data types), `t="d"` cells, cells under a merge (its top left cell is open), table header and totals rows (Excel repairs a changed header), pivot table areas, array formula ranges, locked cells of a protected sheet. Whole sheets: chart, dialog and macro sheets, rows or cells without an address, comments, CDATA, DOCTYPE, PIs or a non-UTF-8 part, a part over 32 MB, over 10000 merges and array ranges or 1000 tables (`large`). Not at all: a signed package (`_xmlsignatures/`, `signed`) and a workbook with a password to modify (`fileSharing` with `hashValue`/`reservationPassword` in xl/workbook.xml, `password`, #1139; read-only recommended alone is open). `pg_office_xlsx_write_reserved()` skips the attributes before the password one quoted value at a time, so a raw `>` in an earlier value (`userName="a>b"`, allowed by XML) no longer hides it (#1160), and a password name inside another value does not count; a PCRE limit error on a huge start tag counts as reserved. 5000 cells a save, 32767 characters a cell. Unreadable styles, tables, a row over 8 MB: docs/memory/platform/office-dom-ceiling.md.

## Which cells of a protected sheet are open?
- The save's rule (`pg_office_xlsx_patch_row()`): a cell in the file is open when its own style has locked="0" (`pg_office_xlsx_style_kinds()` `open`); a cell the file does not hold takes the row's own style (customFormat) when there is one, else the column's (`<cols>`, `pg_office_xlsx_new_cell_style()`), else the default style (cellXfs 0), and is open when that style is unlocked.
- Column styles come from one reader, `pg_office_xlsx_column_styles()`: every `<cols>` list in order, the later entry for a column wins, an entry without a style (or style 0) means the default. The save and the preview both use it (`pg_office_xlsx_sheet_edit_info()` passes it to `pg_office_xlsx_scan()`).

## What does the grid get, and in which order does it look?
- Under protection only: `open` (cells in the file with an unlocked style), `closed` (cells in the file that keep a locked style although their row, column or the default style would open a new one), `rows` (row => true/false for rows with a style of their own, up to the rows shown), `columns` (0-based ranges whose column style is unlocked, cut to the columns shown), `closed_columns` (ranges whose column style is locked; sent only when the default style is unlocked) and `other` (the default style is unlocked). `openOf()` in `pg_office_view.js` checks them in that order and ends with `!!edit.other`.
- `tests/office_edit_locks_test.php` runs a PHP copy of the grid rule against `pg_office_xlsx_patch_sheet()` on every drawn cell of form templates.

## How wide is the grid drawn?
- As wide as the longest shown row (`pg_office_xlsx_rows()` pads a row only to its last cell in the file), and, on a protected sheet offered for editing, out to the last column of `columns` (at most 200). Rows and the default style do not widen it.
- Limit: a column with no cell in any shown row and no unlocked column style is not drawn (e.g. empty cells right of the data when only the default style is unlocked).

## What did this fix?
- #1147: a form template unlocked by column (Excel writes `<col style>` and no `<c>` for its empty cells) was drawn locked, while the save accepted the same cells.
- #1159: an open column with no cell at all (labels in A, B unlocked and empty) was not drawn; with an unlocked default style, empty cells were drawn locked but saved; a column unlocked in a second `<cols>` list was drawn open but refused by the save (only the first list was read).
