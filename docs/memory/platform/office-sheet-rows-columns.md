---
name: office-sheet-rows-columns
description: Insert and delete a row or column of an .xlsx in the file window and a channel's preview (OE1): the head menu, what moves, what is refused and why, one change a save
module: office_shift
---

## How does a person insert or delete a row or column?
- In an .xlsx open for editing (the file window, a channel's preview; same `pg_office_view.js`), a row's number and a column's letter are buttons: their menu offers "Insert a row above row N" / "Delete row N" (or the column's pair). Right click on a cell, the menu key or Shift+F10 offer the cell's row and column together.
- The choice waits as the save's one change: its row or column is marked (a line before it, or struck through), the status line says what happens on save, Esc or the menu's Undo drops it. While it waits no cell is typed in; while cells are changed the menu says to save them first. Not offered on a protected sheet.
- The save sends `cells: [{sheet, name, op: insert|delete, row: 7}]` (or `column: "C"`) to the endpoints cell edits use (`explorer_file_save`, `ws_file_office_save`); `pg_office_xlsx_save()` hands it to `pg_office_xlsx_shift_save()`, under the same lock, version check and package rebuild. No new endpoint, permission or schema.

## What moves?
- `includes/fn/office_shift.php`: row and cell addresses past the line, a deleted row or cell taken out, the sheet's own formula references (also `Sheet!A1` and `'Sheet'!A1` of itself).
- Also moved: shared and array formula ranges, `dimension`, views' `topLeftCell`, `activeCell` and `sqref`, `cols` widths (columns), `mergeCell`, `hyperlink` refs, `ignoredError` sqrefs, row or column page breaks, and this sheet's `calcChain.xml` entries (a dropped entry's sheet id is written on the next one). Text in quotes, structured, other-sheet and other-workbook references stay.
- A range moves as Excel moves it: an insert inside widens it, a delete inside narrows it; a reference to a deleted cell or wholly deleted range becomes `#REF!`. A row's `spans` hint is taken out on a column change.
- Formulas are not worked out: every worksheet loses its stored formula results and the workbook gets `fullCalcOnLoad`, as after a cell edit (`office-cell-edits.md`). Rows with nothing to move are carried byte for byte; every other part of the package is untouched.

## What is refused, with its reason?
- A table or pivot table on the sheet; a defined name local to it or naming it; another worksheet's formulas or link locations, a chart, a pivot cache or a table naming it (cell text does not count).
- A merge or array formula across the line; a shared (filled) formula whose cells or relative references would sit on both sides of the line; a 3D reference through the sheet or a What-If data table.
- Conditional formatting, data validation, a filter, drawings, comments or controls, protected ranges and other parts listed in `pg_office_shift_refused_parts()`, extension lists with ranges; a protected sheet; a cell in the last row or column on insert. Plus every refusal of a cell save (signed, password, large, unusual).
- Messages: `pg_office_shift_message()`; the file stays as it was.

## What was checked?
- `tests/office_shift_test.php`: references, formulas, rows and columns inserted and deleted on fixture sheets, every refusal, a saved package read back by `pg_office_read()` with the locks where the cells went and untouched parts byte for byte. An openpyxl book shifted and read by openpyxl and LibreOffice 24 (merge, widths, `#REF!`). The grid menu was tried in headless Chromium on a static page; not in a sandbox, not in Excel.

## What is left?
- Several rows at once, moving conditional formatting, validations, filters and drawings with the line: not built.
