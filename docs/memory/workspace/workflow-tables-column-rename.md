---
name: workflow-tables-column-rename
description: Renaming a workflow table column that formulas name (#9029): formulas rewritten with the new name in one transaction; a name that would change one is refused
---

## What happens to formulas when a column is renamed?
- Every other formula column of the table that names it is rewritten with the new name, as a spreadsheet does: `Adet * Tutar ₺` becomes `Miktar Ğ * Tutar ₺` (`ws_wtable_formulas_renamed()` in `wtable_rows.php`, `ws_calc_rename()` in `calc.php`). Before #9029 only the column's `name` changed, and since formulas are bound by name on every read, each one naming it showed `#REF` until the old name came back.
- Only the places where the old name is bound are rewritten: the same boundaries and longest-first order as `ws_calc_bind()` (both read `ws_calc_bind_spans()`), so `Net total` stays whole when `Net` is renamed, a call `ADET(…)` (the count function) stays, and a name typed in another case (`adet`) is rewritten too.
- The renamed column's own expression, when it is a formula, is what the form or the API sends.

## When is a rename refused?
- When the formulas, bound with the new names, would read anything else: every place bound before must read the same column after. 409 `name_changes_formula`, field `name`, `formula_names` listing the formula columns. Cases: another column has the new name (of two columns of one name the later is meant), or the new name starts with a figure or sign and would take a figure out of a formula (`Toplam - 2` when a column is named `2`).
- Allowed: a new name starting with a letter that now binds text no column had (a `ref` error before), so a rename can mend a formula typed with a name nobody gave.
- 409 `name_formula_too_long` when a rewritten formula would pass `ws_wtable_limits()['formula']` (1000).
- An options-only change, or the same name, checks and rewrites nothing.

## How is it written?
- `ws_wtable_column_update()`: a rename opens a transaction, reads the table's columns `FOR UPDATE` (`ws_wtable_columns_locked()`), runs `ws_wtable_column_update_check()` again over them, writes the name and each rewritten formula's `options`, commits, then bumps the table's `revision`. A formula added meanwhile is rewritten too, or refuses the rename. Nothing is written when refused.
- No schema change; formula values are still never stored (`workflow-table-rows.md`).

## What do the screen and the API show?
- Screen: "The column was saved. These formulas now use its new name: …"; a refusal names them after its sentence ("Formulas: …", `ws_wtable_screen_refusal_text()`).
- API and MCP (`workspace.table_columns.update`): the dry run lists the rewritten formula columns by id in `detail.formulas`; the refusals are 409 with the formula names in the message.

## Tests
- `tests/workspace_wtable_rename_test.php` (the write runs in a PHP process of its own with a fake db layer).
