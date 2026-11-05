---
name: list-table-quick-edit-currencies
description: All Currencies list quick edit (includes/fn/currency_list_edit.php): the fields, the screen's checks and write path, the base rate kept as text, the base move that redraws the other rows
module: currency_list_edit
---

## Which cells change?
- Every value `edit_currency.php` changes (`pg_currency_edit_columns()`): Name, Base (Yes/No), Code, Symbol (the stored HTML, as the screen keeps it), Exchange Rate. Text: Created and Last Modified. `tests/currency_list_edit_test.php` fails for a new column that is neither.
- The base currency's rate stays text (`pg_currency_quick_locked()`): the screen always saves it as 1.

## Are the checks the screen's checks?
- Yes, the helpers of `includes/forms/currency_form.php`: name required; code trimmed, in capitals, three Latin letters (`pg_currency_form_code_error()`) and not another currency's (`pg_currency_form_code_in_use()`); the base cannot be switched off (`pg_currency_form_base_error()`); a rate above zero and within decimal(10,5) (`pg_currency_form_exchange_rate_error()`), saved with five places. A currency made the base gets the rate 1.
- Rights: role below User or `manage_ecommerce`, as `validate_ecommerce_access()`; `view_currencies.php` answers `pg_list_edit_request()` before its own access check.

## How is it written and drawn?
- `pg_currency_quick_update()` writes the stored row with the one change through `pg_store_currency_save()` (one base, last change stamped, `currency.updated`), and logs the screen's "was modified" line. The same value again writes nothing.
- The cells come from `pg_currency_list_cells()`, the list's own drawing. When the base moves or the base's code changes, the answer's `rows` redraws every other currency: the old base loses its mark, each reverse rate ("1 EUR = 1,0870 USD") names the new base.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md
