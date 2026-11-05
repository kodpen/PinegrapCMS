---
name: list-table-quick-edit-states
description: All States list quick edit (includes/fn/state_list_edit.php): name, code and country change with the state screen's checks; a state whose country is gone keeps the Country cell as text
module: state_list_edit
---

## Which cells change?
- Every value `edit_state.php` changes (`pg_state_edit_columns()`): Name, Code, Country (the screen picker's countries, `pg_state_quick_countries()`: the panel-language name, sorted, a shared name carrying its code). Text: Last Modified. `tests/state_list_edit_test.php` fails for a new column that is neither.
- A state whose country is gone keeps its Country cell as text: the picker does not offer the stored id, and the screen asks for a country first.

## Are the checks the screen's checks?
- Yes, `includes/forms/state_form.php`: the stored state with the one change goes through `pg_state_form_values()` (trimmed), `pg_state_form_required_errors()`, `pg_state_form_code_error()` (not another state's code in that country; asked again when the country changes, so a move into a country with the code is refused) and `pg_state_form_country_error()`. The first error is the refusal.
- Rights: role below User or `manage_ecommerce`, as `validate_ecommerce_access()`; `view_states.php` answers `pg_list_edit_request()` before its own access check.

## How is it written and drawn?
- `pg_state_quick_update()` writes through `pg_store_state_save()` (last change stamped) and logs the screen's "was modified" line; the same value again writes nothing. The answer is the row read again (`pg_state_list_cells()`, the list's own drawing).

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md
