---
name: workflow-tables-numbers
description: How workflow tables read and show amounts and numbers (#3391, PR #3507, import PR #3521): ws_calc_number() for typed figures, site separators in file import and on display, machine form stored
---

## How is a typed amount or number read?
- `ws_wtable_screen_cell_from_form()` reads money and number fields with `ws_wtable_screen_figure()`, which is `ws_calc_number()` (the reader of the workspace's sums, `workspace-calc-numbers.md`) with a `%` refused. One rule for the whole module: a figure in a table reads as it does in a hesap block.
- Turkish site: `1.250` = 1250, `1.250,50` = 1250.50, `12,50` and `12.50` = 12.5. English: `1,250.50` = 1250.50, `1,250` = 1250, `1.25` = 1.25. A currency mark (₺, TL, $) around it is dropped.
- What it cannot read goes to the store as typed (`not_minor_units`, `not_number`). The store keeps the machine form: money in minor units, numbers as int/float. No data changed.

## How are they shown?
- `ws_wtable_screen_figure_text()` writes a machine figure with the separators of `ws_calc_decimal_comma()`: the list, the board and formula results grouped (`1.250,50 TRY`, `1,234.5`); the edit field ungrouped (`1250,50`), since a lone group of three digits would read back as thousands (a 3-decimal amount `1,234` reads back as 1.234 in Turkish).
- The currency stays the column's code after the figure. The `not_minor_units` hint names the sample the site's way ("Write an amount, such as {var:1}.", 12,50 / 12.50).
- Tests switch the language with `ws_calc_decimal_comma(true|false)` (`tests/workspace_wtable_screen_test.php`).

## What was the symptom?
- Turkish panel: `1.250` saved as 1,25 TRY without a warning, `1.250,50` refused ("12.50 gibi bir tutar yazın."), amounts shown `1.25 TRY`, numbers `1234.5`. The form reader turned `,` into `.` and matched a dot-decimal pattern; the list wrote `number_format($value, 10, '.', '')`.

## How does the file import read numbers?
- `ws_wtable_import_number()` (`workflow-table-import.md`, PR #3521) reads a lone mark before three digits by the site's language, as `ws_calc_number()` does: Turkish `1.250` = 1250, `1,250` = 1.25; English `1,250` = 1250, `1.250` = 1.25. A lone mark not before three digits stays the decimal (`1,25`, `1.25`); both marks and a repeated mark read as before. A head of four digits or a lone `0` is no thousands group (`1234.567`, `0.250` keep decimals). The type guess is unchanged; amounts with a currency go through the same reader.
- Before, a lone mark was always the decimal: a Turkish file's `1.250` came in as 1.25.
- The API row writer takes machine form only and is right as it is.
