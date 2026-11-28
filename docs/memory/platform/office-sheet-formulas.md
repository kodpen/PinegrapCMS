---
name: office-sheet-formulas
description: Start file for simple formulas in the Excel editor (SUM, AVERAGE, MIN, MAX, COUNT, + - * /, TOPLA): the decision, what is simple, which file holds the reader, save and grid
---

## What is it?
- A workbook cell typed with a leading `=` in the file window or a channel's preview is a formula when it is simple; the cell shows its value, its editor the formula. The save writes it as Excel does (`<f>` in English with its `<v>`) and works out every simple formula of a changed sheet again.
- Owner (2026-11-26, agent channel poll 235, "Tablo: basit formüller"): simple formulas are built with the product's own code; every other formula stays locked and loses its stored result as before (`workflow/decisions-made-product-115.md`). It changed the 2026-11-19 scope that built no engine (`office-files-scope.md`).

## What is a simple formula?
- Numbers, references and ranges of the same sheet, `+ - * /`, brackets, a leading minus, SUM, AVERAGE, MIN, MAX, COUNT; English names or the panel's language (Turkish TOPLA, ORTALAMA, MİN, MAK, SAY), arguments parted by `,` or `;`. The exact rules and Excel's limits: `office-formula-engine.md`.
- Text and empty cells count as Excel counts them; a division by zero, a text operand or a circle gives the cell `#DIV/0!`, `#VALUE!` or `#REF!`, never a refusal of the save.

## Which file holds what?
- The reader and calculator, pure functions in `includes/fn/office_formula.php` (SF1, #12192): `office-formula-engine.md`.
- The save: typed formulas, which formula cells are open, the dependency order, circles, what still loses its result, the formula limits a sheet: `office-sheet-formula-save.md` (SF2, #12207).
- The grid: typing, the editor in the panel's language, the refusal in the status line, values a waiting change reaches drawn at once by the twin `assets/js/pg_office_formula.js`, fill down and copy of a formula cell: `office-sheet-formula-view.md` (SF3, #12222).
- Locks of the other formula kinds: `office-cell-locks.md`; results stripped on the other sheets and after a row or column shift: `office-cell-edits.md`, `office-sheet-rows-columns.md`.

## What is never built?
- Other functions, other sheets' references, names, array formulas, moving a formula's references on fill down or paste, charts, pivot tables, page layout (`office-files-scope.md`).

## How is a new function added?
- Only by a new owner decision (the scope is the five functions). It would go in the reader, its value rule and the names per language in `office_formula.php`, the same in the twin, with cases in `tests/office_formula_test.php` that `tests/office_formula_twin_test.php` runs through both.
