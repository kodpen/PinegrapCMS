---
name: office-cell-edits
description: Excel cells changed in the file window (includes/fn/office_edit.php, PR #656): what may change, how a typed value is stored, the row splice, why formula results are taken out
---

## How is a cell of a workbook changed?
- The file window asks `explorer_office_preview` with `edit: 1`. For someone who may change the file (`pg_explorer_file_editable_by()`, never an ERP document) each sheet carries `edit` (ok or why not, locked cells with a reason, formula texts, unlocked cells of a protected sheet, ranges cut to the rows and columns shown) and the book its `version` (sha1 of the bytes, taken before reading). `pg_office_view.js` draws the grid: double-click, F2/Enter or typing edits, Enter/Tab/arrows move, Delete empties, Esc undoes, Alt+Enter breaks a line, Ctrl+S saves; retyping the file's value drops the change.
- The window's Save sends `cells` (sheet position, name, cell, value) and `office_version` with `explorer_file_save`, which writes them before any rename: a refusal leaves the file, its name and its row as they were. `pg_office_xlsx_save()`: a lock file in data/temp (5 s, removed on release), the version must still be the file's ("changed after you opened it"), the sheet name must match; the new package is built as a copy in data/temp, read back, renamed over the old one. When Windows refuses the rename it is copied in place (old bytes kept, restored on failure); the only whole copy is never deleted.

## What is never changed?
- Formulas, linked values, merges, tables, pivots, array formulas, locked cells of a protected sheet (and which cells of one are open), unusual or large parts, signed or password-reserved workbooks: docs/memory/platform/office-cell-locks.md.

## How is a typed value stored?
- Only rows with a change are parsed (DOM in a wrapper carrying the root's namespaces, so a prefixed sheet works) and written back; the rest is copied byte for byte. A new cell goes in column order, styled like the row (customFormat) or the column.
- Text is an inline string: the shared string table and its counts are never touched (as openpyxl writes).
- A cell keeps its kind: text stays text; a number or empty cell takes a number (dot, or a lone comma as decimal mark; leading zeros and over 15 digits stay text); a date-styled cell takes 2026-10-19 or 19.10.2026, with a time, as a day count (1904 books too); a yes/no cell TRUE/FALSE/DOĞRU/YANLIŞ/1/0; a text-formatted (@) cell always text; a leading "=" stays text.

## Why are formula results taken out of every sheet?
- LibreOffice trusts a cached result even with `fullCalcOnLoad="1"` (24.2: a total stayed stale) but works out a formula that has none; Excel honours the flag. So the save removes every worksheet's cached results (`pg_office_xlsx_strip_formula_values()`, `vm` cells kept) and sets `calcPr fullCalcOnLoad="1"`, as openpyxl does. The preview marks such a cell ƒx (`rows[].calc`); readers of cached values (pandas, the importers) see it empty until a spreadsheet saves it.

## What was checked, and what not?
- `tests/office_edit_test.php`; openpyxl-, LibreOffice- and Excel-written books edited, read by openpyxl, recalculated by LibreOffice 24.2; the window in a sandbox with Playwright (grid, locks, protected sheet, stale version). Not checked: Excel itself, Numbers, Google Sheets. In a channel: docs/memory/workspace/workspace-office-edits.md; Word paragraphs: docs/memory/platform/office-paragraph-edits.md.
