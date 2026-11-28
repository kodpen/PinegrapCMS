---
name: office-cell-edits
description: Excel cells changed in the file window (office_edit.php, PR #656): what may change, how a typed value is stored, the row splice, why formula results are worked out or taken out
---

## How is a cell of a workbook changed?
- The file window asks `explorer_office_preview` with `edit: 1`. For someone who may change the file (`pg_explorer_file_editable_by()`, never an ERP document) each sheet carries `edit` (ok or why not, locked cells with a reason, formula texts, open cells of a protected sheet) and the book its `version` (sha1 of the bytes taken before reading; not `files.timestamp`, which a move also changes).
- `pg_office_view.js` draws the grid: double-click, F2/Enter or typing edits, Enter/Tab/arrows move, Delete empties, Esc puts back the opened value, Alt+Enter breaks a line, Ctrl+S saves (Undo: `office-sheet-undo.md`); retyping the file's value drops the change.
- The window's Save sends `cells` (sheet position, name, cell, value) in the one list of changes (`office-sheet-change-list.md`, `office-save-list.md`) and `office_version` with `explorer_file_save`, which writes them before any rename: a refusal leaves the file, its name and its row as they were.
- `pg_office_xlsx_save()`: a lock file in data/temp (5 s), the version must still be the file's ("changed after you opened it"), the sheet name must match; the new package is built as a copy in data/temp, read back, renamed over the old one. When Windows refuses the rename it is copied in place (old bytes restored on failure).

## What is never changed?
- Formulas that are not simple, linked values, merges, tables, pivots, locked cells of a protected sheet, large parts, signed or password-reserved workbooks: `office-cell-locks.md`.

## How is a typed value stored?
- Only rows with a change are parsed (DOM keeping the root's namespaces) and written back, a row the sheet lacks made (PR #11928); the rest is copied byte for byte. A new cell goes in column order, styled like the row (customFormat) or the column.
- Text is an inline string: the shared string table and its counts are never touched (as openpyxl writes).
- A cell keeps its kind: text stays text; a number or empty cell takes a number (dot, or a lone comma as decimal mark; leading zeros and over 15 digits stay text).
- A date- or time-styled cell (`pg_office_xlsx_date_styles()`, h/s formats too) takes 2026-10-19 or 19.10.2026, with a time, as a day count (1904 books too), and a time alone (08:45, 8:45:30, 25:30 for [h]) or the preview's day-0 form of a time cell (1899-12-30 08:30, #773) as a day fraction (#1151; before, a time was saved as text and left out of sums).
- A yes/no cell TRUE/FALSE/DOĞRU/YANLIŞ/1/0.
- A text-formatted cell is always text: `@` in the format's first section (`pg_office_xlsx_text_format()`; `;` and `@` in quotes, brackets, `\` escapes, `_x`/`*x` are characters), so accounting formats (`…;_-@_-`) and Excel's `d/m/yy;@` dates take numbers and dates (#837, PR #1261); a leading "=" is a simple formula when it reads as one, else text (`office-sheet-formula-save.md`).

## Why are formula results worked out or taken out?
- On a sheet the save changed, simple formulas get their result worked out again (`office-sheet-formula-save.md`); every other formula and sheet is stripped:
- LibreOffice trusts a cached result even with `fullCalcOnLoad="1"` but works out a formula that has none; Excel honours the flag. So the save removes the other cached results (`pg_office_xlsx_strip_formula_values()`, `vm` cells kept) and sets `calcPr fullCalcOnLoad="1"`, as openpyxl does. The preview marks such a cell ƒx (`rows[].calc`); readers of cached values (pandas, the importers) see it empty until a spreadsheet saves it.

## What was checked, and what not?
- `tests/office_edit_test.php`; openpyxl-, LibreOffice- and Excel-written books edited, read by openpyxl, recalculated by LibreOffice 24.2; the window on a sandbox. Not checked: Excel, Numbers, Google Sheets. In a channel: `workspace/workspace-office-edits.md`; Word paragraphs: `office-paragraph-edits.md`.
