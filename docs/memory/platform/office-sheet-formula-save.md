---
name: office-sheet-formula-save
description: Simple formulas in a workbook save (office_edit.php, SF2): typed "=" formulas, open formula cells, the changed sheet worked out in dependency order, circles, what loses its result
module: office_edit
---

## When does a typed text become a formula?
- `pg_office_xlsx_typed_formula()`: the text starts with `=` and `pg_office_formula_parse()` reads it as simple (`office-formula-engine.md`), with the English names or the panel's language (`lang(array('info' => true))`, passed to the row writer as `language`). It is written as `<f>` in Excel's form (English, commas, no `=`); its `<v>` comes from the work-out below.
- A cell formatted as text (`@`) keeps `=…` as text, as Excel does. A leading `=` that is not a simple formula is stored as text, as before; refusing it in the status line is the view's job (SF3).
- Typing a value into a simple formula's cell replaces the formula; the old cached result does not decide the kind (the cell counts as empty). Emptying it removes the cell when nothing else is left.

## Which formula cells are open?
- `pg_office_xlsx_simple_formula()`: a formula of its own cell (`t` empty or `normal`, no `ref`, no `si`), no `vm` on the cell, text read as simple with the English names. Shared, array and data-table formulas, linked values and every other function stay locked (`office-cell-locks.md`).
- The grid's scan reads up to 8192 characters of a formula to decide; a longer one, or one past the sheet's formula text budget, stays locked. The formula text still goes to the grid (`formulas`), cut to the label.
- A protected sheet's lock still applies to an open formula cell.

## How are the results worked out on a save?
- `pg_office_xlsx_work_formulas()` replaces the plain strip on every sheet the step changed: cells read once with a reader (`pg_office_xlsx_formula_cells()`), shared strings read only if a cell holds one (preview limits), results by `pg_office_xlsx_formula_results()`, written into the text cell by cell (`pg_office_xlsx_write_formula_results()`), the rest byte for byte.
- Order: Tarjan's strongly connected parts over "reads a formula cell", dependencies first. A part of two or more formulas, or one reading its own cell, is a circle: `#REF!` (t="e"). A formula reading a circle gets the error through arithmetic; COUNT counts past it.
- A result is written as Excel writes it: number (15 digits, 17 when needed to read back the same), `t="str"` text, `t="b"`, `t="e"`.

## What still loses its result?
- Every formula that is not simple, and a simple one reading a cell whose value is unknown here: another formula's stripped result, a shared string when the table cannot be read, a `t="d"` or `vm` cell, a non-numeric number cell.
- Sheets the step did not change are stripped as before (`office-cell-edits.md`); a later row/column shift in the same save strips all sheets again. `fullCalcOnLoad="1"` stays set.
- Past 5000 simple formulas (`PG_OFFICE_EDIT_MAX_FORMULAS`) or 200000 value cells (`PG_OFFICE_EDIT_MAX_FORMULA_CELLS`) a sheet is only stripped.

## What was checked?
- `tests/office_edit_formula_test.php`: typed formulas (Turkish names, text cell, not simple), the scan's locks, a formula cell changed, a chain of three, a circle, a mixed sheet (shared strings, text, logical, error, not-simple dependency, linked, shared formula), a save read back by `pg_office_read()`. Not opened in Excel or LibreOffice in this step.
