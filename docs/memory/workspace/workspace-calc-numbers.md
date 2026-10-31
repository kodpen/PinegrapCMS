---
name: workspace-calc-numbers
description: Figures inside a workspace sum (ws_calc_tokens(), ws_calc_inline(), #3398): read the site's way, leading %, English comma separates arguments, "=" line errors
---

## How is a figure inside an expression read?
- `ws_calc_tokens()` reads it with `ws_calc_figure_at()`, by the site's separators (`ws_calc_decimal_comma()`), as `ws_calc_number()` reads a lone figure.
- Turkish: `15.000` is fifteen thousand, `1.250,50` has decimals, `1.5` (dot not before three digits) is one and a half. One comma per figure, so `SUM(1,2,3)` is `SUM(1,2; 3)`.
- English: a dot is the decimal. A comma groups thousands only outside every bracket (`1,250.50 * 2`); inside a function it separates arguments: `MAX(10,20)` 20, `SUM(1,2,3)` 6, `IF(1>0,5,6)` 5, `SUM(1,000)` 1. `1,5` outside a function is a syntax error.
- `;` separates arguments in both languages; `, ` (comma and space) too, also in Turkish.
- The same reader works formula columns of workflow tables (`ws_wtable_formula_values()`), hesap blocks and Markdown table `=` cells.

## What is a leading %?
- A `%` where a value is due (start, after an operator, `(` or `;`) followed by a figure is part of it: `%20` is 0.2, as `ws_calc_format_percent()` writes Turkish shares. After a value `%` stays that value's percent sign (`20%`).

## What does an "=" line do with a mark it cannot read?
- `ws_calc_inline()` collects `%?figure%?`; a lone `%` or a figure `ws_calc_number()` cannot read (`1.2.3`) makes the line `syntax` instead of being dropped (it used to drop `%`, so `1.000 * %20 =` gave 20.000).
- Its `source` writes the figures the site's way (`1000 + 0,125` in Turkish), since the formula reader reads them so.

## What was the symptom?
- Turkish: `15.000 + 750` gave 765, `1.250,50` was a syntax error, `1.000 * %20` an error. English: `MAX(10,20)` gave 10.2. The tokenizer read `[0-9]*([.,][0-9]+)?` and turned `,` into `.` whatever the language.

## How is it tested?
- `tests/workspace_calc_numbers_test.php` runs each case on both sites through `ws_calc_decimal_comma(true|false)`, the setter the function takes for tests.
