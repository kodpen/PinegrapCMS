---
name: office-cell-edits
description: Excel cells changed in the file window (includes/fn/office_edit.php, PR #656): what may change, how a typed value is stored, the row splice, why formula results are taken out
---

## How is a cell of a workbook changed?
- The file window asks `explorer_office_preview` with `edit: 1`. For someone who may change the file (`pg_explorer_file_editable_by()`, never an ERP document) each sheet carries `edit` (ok or why not, locked cells with a reason, formula texts, unlocked cells of a protected sheet, ranges) and the book its `version` (sha1 of the bytes). `pg_office_view.js` draws the grid: double-click, F2/Enter or typing over a cell edits, Enter/Tab/arrows move, Delete empties, Esc undoes, Alt+Enter is a line break, Ctrl+S saves; retyping the file's value drops the change.
- The window's Save sends `cells` (sheet position, name, cell, value) and `office_version` with `explorer_file_save`, which writes them before any rename: a refusal leaves the file, its name and its row as they were. `pg_office_xlsx_save()`: a lock file in data/temp (5 s), the version must still be the file's ("changed after you opened it" otherwise), the sheet name must match, then the new package is built as a copy in data/temp, read back, and renamed over the old one (copy on Windows when the rename is refused), its mode kept.

## What is never changed?
- Formula cells, `vm` cells (IMAGE(), data types), `t="d"` cells, cells under a merge (its top left cell is open), table header and totals rows (Excel repairs a file whose header differs from the table part's column name), pivot table areas, array formula ranges, locked cells of a protected sheet (a style with protection locked="0" opens a cell). Whole sheets: chart, dialog and macro sheets, rows or cells without an address, comments/CDATA/DOCTYPE or a non-UTF-8 part, a part over 32 MB; a signed package (`_xmlsignatures/`) not at all. 5000 cells a save, 32767 characters a cell.

## How is a typed value stored?
- Only rows with a change are parsed (DOM inside a wrapper that carries the root's namespace declarations, so a prefixed sheet works) and written back; the rest of the sheet is copied byte for byte. A new cell goes in column order, styled like the row (customFormat) or the column.
- Text is an inline string: the shared string table and its counts are never touched (openpyxl and POI streaming write the same).
- A cell keeps its kind: text stays text; a number or empty cell takes a number (dot, or a lone comma as decimal mark; leading zeros and over 15 digits stay text); a date-styled cell takes 2026-10-19 or 19.10.2026, with a time, as a day count (1904 books honoured, also by the reader now); a yes/no cell TRUE/FALSE/DOĞRU/YANLIŞ/1/0; a text-formatted (@) cell always text; a leading "=" is text, no formula is written.

## Why are formula results taken out of every sheet?
- LibreOffice trusts a cached result even with `fullCalcOnLoad="1"` (24.2: a total stayed stale) but works out a formula that has none; Excel honours the flag. So the save removes every worksheet's cached results (`pg_office_xlsx_strip_formula_values()`, `vm` cells kept) and sets `calcPr fullCalcOnLoad="1"`, as openpyxl does. The preview marks such a cell ƒx (`rows[].calc`); readers of cached values (pandas, the product importers) see it empty until a spreadsheet program saves the file.

## What was checked, and what not?
- `tests/office_edit_test.php`; openpyxl-, LibreOffice- and Excel-written books (PhpSpreadsheet templates) edited, read by openpyxl and recalculated by LibreOffice 24.2; the window in a sandbox with Playwright (grid, locks, protected sheet, stale version). Not checked: Excel itself, Numbers, Google Sheets. The same edit on a file posted in a channel: docs/memory/workspace/workspace-office-cell-edits.md.
