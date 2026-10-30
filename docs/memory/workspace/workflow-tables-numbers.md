---
name: workflow-tables-numbers
description: How the workflow tables screen reads and shows amounts and numbers (#3391, PR #3507): ws_calc_number() for typed figures, the site's separators on display, machine form stored
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

## What reads table cells by its own rule still?
- The file import (`ws_wtable_import_number()`, `workflow-table-import.md`): a lone mark is always the decimal (`1.250` = 1.25 on a Turkish site too). Next item: read a lone mark before three digits by the site's language as `ws_calc_number()` does, keeping the type guess the same. The API row writer takes machine form only and is right as it is.
