---
name: office-sheet-formulas
description: Plan (owner 2026-11-26, poll 235): simple formulas in the spreadsheet editor: SUM, AVERAGE, MIN, MAX, COUNT and the four operations, worked out again when a cell changes; steps SF1 to SF4
priority: 6
---

## What is asked?
- Owner (2026-11-26, agent channel poll 235, message 14401): "Tablo: basit formüller", with the other seven abilities. It changes the scope decision of 2026-11-19, which built no formula engine (`workflow/decisions-made-product-115.md`, `platform/office-files-scope.md`).
- Today a formula cell is locked, and its stored result is taken out when a cell it may depend on changes (`platform/office-cell-edits.md`, `office-cell-locks.md`).

## When does it start?
- After SA5 of `plans/office-sheet-abilities.md`: the grid's script is one file. Until then no step is free: the architect marks lines `free` when SA5 has landed.

## What is a simple formula?
- Numbers, cell references and ranges of the same sheet (`A1`, `$A$1`, `A1:B9`), `+ - * /`, brackets, a leading minus, and SUM, AVERAGE, MIN, MAX, COUNT with any number of arguments.
- Typed with the function's English name or its name in the panel's language (Turkish: TOPLA, ORTALAMA, MİN, MAK, SAY), arguments parted by `,` or `;`; stored as Excel stores it (English, commas).
- Text and empty cells count as Excel counts them (left out of a range, an error in arithmetic). A division by zero, a text operand or a circle gives the cell Excel's error value (`#DIV/0!`, `#VALUE!`, `#REF!`), never a refusal of the save.
- Anything else (another function, another sheet, a name, an array formula) is not a simple formula: it stays locked and loses its result as today.

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- SF1 (M, new `includes/fn/office_formula.php`): pure functions: read a formula's text into a tree or "not simple", the cells it reads, its value from a cell lookup, the function names per language. Tests: each rule above, Excel's own results as the expected values.
- SF2 (M, `includes/fn/office_edit.php`): a cell change may be a formula: written as `<f>` with its value. On a save every simple formula of a changed sheet is worked out again in dependency order; the others lose their result as today. A simple formula's cell is no longer locked. Tests: saved, read back, a chain of three, a circle, a mixed sheet.
- SF3 (M, `pg_office_view.js` and a twin `assets/js/pg_office_formula.js`): a cell typed with a leading `=` is a formula. The cell shows its value, its editor the formula; values that depend on a waiting change are drawn again at once from the twin. A text that is not a simple formula is refused in the status line with what is allowed. The twin and the PHP run the same test cases (`core/` twin rules).
- SF4 (S) Close-out: `platform/office-files-scope.md`, `office-cell-edits.md`, `office-cell-locks.md` and `office-overview.md` corrected, a new `platform/office-sheet-formulas.md`; this file deleted.

## What does every step keep?
- What the steady editor keeps (`platform/office-overview.md`): Save a button, no library, no schema, every text through `lang()`, look A. Charts, pivot tables and page layout stay never built.
- A formula Excel wrote that is simple gives the value Excel stored for it; the tests compare on files Excel and LibreOffice saved.
- SF3 is tried in a browser on a sandbox at 1440 and 390 px; the PR says what was tried.

Related: docs/memory/plans/office-sheet-abilities.md, docs/memory/platform/office-cell-locks.md
