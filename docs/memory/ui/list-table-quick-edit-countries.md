---
name: list-table-quick-edit-countries
description: All Countries list quick edit (includes/fn/country_list_edit.php): the fields, the screen's checks and write path, the new default that redraws the old one
module: country_list_edit
---

## Which cells change?
- Every value `edit_country.php` changes (`pg_country_edit_columns()`): Name, Code, Zip Code Required, Extra Delivery Days, Default. Text: Last Modified. `tests/country_list_edit_test.php` fails for a new column that is neither.

## Are the checks the screen's checks?
- Yes, `includes/forms/country_form.php`: the stored country with the one change goes through `pg_country_form_values()` (name and code trimmed), `pg_country_form_required_errors()` (name, code, whole days of 0 or more; empty days are 0) and, for the code, `pg_country_form_code_error()` (not another country's). The first error is the refusal.
- Rights: role below User or `manage_ecommerce`, as `validate_ecommerce_access()`; `view_countries.php` answers `pg_list_edit_request()` before its own access check.

## How is it written and drawn?
- `pg_country_quick_update()` writes through `pg_store_country_save()` (one default, last change stamped) and logs the screen's "was modified" line; the same value again writes nothing.
- The cells come from `pg_country_list_cells()` on rows labelled by `pg_country_list_labelled()` (the panel-language name, a shared name carrying its code), so the answer reads every country again. A new default sends the country that lost the switch under `rows`.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md
