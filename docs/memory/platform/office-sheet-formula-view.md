---
name: office-sheet-formula-view
description: Simple formulas in the workbook grid (SF3): typed with "=", the editor shows the formula, values a change reaches drawn by the twin pg_office_formula.js, refusals
---

## What does the grid do with a formula?
- A cell typed with a leading `=` that the twin reads as simple (`office-formula-engine.md`, English names or the panel's language from `formula_language`) is a formula: the cell shows its value, its title `Worked out by a formula: =…`, its editor the formula. The text is sent as typed; the save stores it in English (`office-sheet-formula-save.md`).
- An open formula of the file opens in its editor as `=` and its text with the language's names (`=TOPLA(D2:D3)`). Typing the same formula again, in either language, is no change; any value replaces it, also one equal to its result, and Delete empties it.
- A text starting with `=` that is not simple is refused: Enter or Tab keep the editor open, leaving the cell (Esc, a click away) puts back what it held, and the status line says what is allowed (`formula_refused`, the names in the panel's language).

## When is a value worked out in the browser?
- `liveResults()`: once the sheet has a typed cell, every simple formula of the sheet goes through the twin's `results()` (the twin of `pg_office_xlsx_formula_results()`: dependency order, a circle `#REF!`). Only the formulas a typed cell reaches, through other formulas too, and the typed formulas are drawn again; the others keep the file's result and its number format's text.
- Not known here, drawn empty with the ƒx mark: a formula reading a date-formatted cell, a formula the file holds that is not simple (its result is stripped on save), a range past the rows of a cut preview, more than 5000 formulas. After a row or column insert or delete waiting on the sheet, the file's formulas are locked (`edit.shifted`): the save moves their references, the grid holds the old texts.
- Values read as the save stores them, roughly: a number text is a number, TRUE/FALSE and error codes of the file as such, other text text. The view does not know a cell's kind, so a typed number into a text cell is counted as a number here and stays text on save. The server's result is the one written.

## How is the twin loaded and tested?
- `assets/js/pg_office_formula.js` (`window.pgOfficeFormula`) is fetched by `pg_office_view.js` from its own folder and stamp (`withFormulas()`), so no screen prints a second tag. Until it is there the grid works as before and the sheet is drawn again when it comes, unless a cell was chosen.
- `tests/office_formula_twin_test.php` runs the cases of `tests/office_formula_test.php` through PHP and Node and compares them: reading, text in English and Turkish, cells, values, number text, a sheet with a chain, a circle and an unknown cell. `tests/office_sheet_formula_view_test.php`: the list of changes around a formula cell and the lock after a move.

## What else changed with it?
- Fill down (Ctrl+D) leaves every formula column as it was, simple ones too (no reference is moved); Ctrl+C copies a formula cell as the value it shows.
- The old status text `formula_text` ("Kept as text…") is no longer used by the grid.
