---
name: office-formula-engine
description: The simple formula reader and calculator (includes/fn/office_formula.php, SF1): what is a simple formula, the tree, cells read, stored text, Turkish names, values as Excel gives them, limits
module: office_formula
---

## What does the module do?
- Pure functions: `pg_office_formula_parse($text, $language)` gives a tree or null ("not simple"); `pg_office_formula_cells()` the areas it reads; `pg_office_formula_reads()` whether it reads one cell; `pg_office_formula_text()` its text as Excel stores it; `pg_office_formula_value($tree, $cells)` its value. Tests: `tests/office_formula_test.php`.

## What is a simple formula?
- Numbers (`1`, `.5`, `1.5E3`), cells of the same sheet (`A1`, `$A$1`, lower case allowed), ranges `A1:B9` only as a function argument (bracketed too), `+ - * /`, brackets, a leading minus or plus (binds closer than `*`, as in Excel: `=2*-3`), SUM, AVERAGE, MIN, MAX, COUNT with one or more arguments, parted by `,` or `;`. A leading `=` is allowed.
- Not simple (null): any other function or name, `TRUE`, another sheet, text constants, `&`, `^`, `%`, comparisons, `A:A` or `1:1`, a bare range (`=A1:B2`), an empty argument (`SUM(A1,)`, `SUM()`), a space between references (intersection), `_xlfn.`, `#REF!` in the text, a cell beyond XFD1048576.
- Excel's limits: 8192 characters, 64 levels of nesting, 255 arguments. A decimal takes a point only: `1,5` is two arguments.

## Which function names are understood?
- English always. `pg_office_formula_names('tr')`: TOPLA, ORTALAMA, MİN, MAK, SAY; another language has the English names only. Case is ignored and Turkish `ı`/`İ` read as `I` (`min`, `MİN`, `mın` are MIN). A Turkish name is not understood when the language given is `en`, so a stored formula is read with `en`.
- `pg_office_formula_text($tree)` writes English names, commas, upper case, no spaces, no `=`, brackets kept; numbers through `pg_office_formula_number_text()` (whole below 1E+15 plain, else 15 significant digits, exponent `1.5E-07`). With `'tr'` it writes the Turkish names for showing.

## What does the tree look like?
- Nodes with `type`: `number`, `cell` (row 1-based, column 0-based, absolute_row, absolute_column), `range` (from, to), `unary`, `binary` (op, left, right), `group` (inner), `function` (English name, arguments).
- `pg_office_formula_cells()` gives `[first row, first column, last row, last column]` per reference, in written order, each once; a range typed `B3:A1` is the same area as `A1:B3`.

## How is the value worked out?
- Cells are `[row => [column => value]]`: float/int, string, bool, null or `''` for empty, an error `['error' => '#N/A']`. A formula cell is given with its worked-out value.
- Arithmetic as Excel: empty is 0, TRUE 1, a text reading as a number (`" 2.5 "`, `"50%"`, `"-3e2"`) that number, other text `#VALUE!`; `x/0` `#DIV/0!`; a result beyond a double `#NUM!`; the first error met, left to right, is the value.
- `=A1` alone (also `=(A1)`) gives the cell as it is: text stays text (`"5"` too), TRUE stays TRUE, empty is 0.
- Functions: a cell or range argument gives its numbers only (text, logical values, empty left out; an error is the result); any other argument is worked out as one number. COUNT counts past errors. No number: SUM, MIN, MAX, COUNT 0, AVERAGE `#DIV/0!`.
- A range visits only the cells given, row by row. Numbers come back as float.
- Circles, order and `<v>`: `office-sheet-formula-save.md`.

## What is not followed?
- Excel's thousands separator, dates and currency signs in a text read as a number (`"1,000"`, `"$5"`) give `#VALUE!` here.
- Excel's 15-digit cancellation of a final subtraction near zero: `=0.3-0.2-0.1` is -2.8E-17 here, 0 in Excel.

## What do the tests compare with?
- Expected values are Excel's results for the same formula on the same cells. The fixtures saved by LibreOffice (`tests/fixtures/erp_bank/statement.xlsx` `SUM(D6:D9)`, `sheet_read/book.xlsx` `B2*2`, `1/0`) are worked out again and match the stored result to 15 digits. No Excel-saved fixture has a formula yet.
