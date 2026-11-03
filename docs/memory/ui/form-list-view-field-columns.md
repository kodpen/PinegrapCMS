---
name: form-list-view-field-columns
description: Which order-by and filter fields a form list view can store, the address_name ENUM widening and the screen's refusal of other values
module: forms
---

## Which fields can a form list view sort and filter by?
- `''` (none), a value of `get_standard_fields_for_view()` (`includes/fn/forms.php`), `random` for the first order-by only, or the id of one of the form's own fields. `pg_form_list_view_field_allowed()` says whether a posted value is one of these; tested in `tests/form_list_view_field_test.php`.
- The standard field columns `form_list_view_pages.order_by_1_standard_field`, `order_by_2_standard_field`, `order_by_3_standard_field` and `form_list_view_filters.standard_field` are ENUMs. A new value in `get_standard_fields_for_view()` needs a migration step that appends it to all four lists, and the install dump `data/backups/turkish_default/sql.sql` the same values.

## What did the address_name fix change (#5697, PR #PRNUM)?
- Symptom: a view set to sort or filter by the address name was saved with an empty field; the output (`get_form_list_view.php`) already handled `address_name`, only the columns could not hold it.
- Migration sub-step `upgrade_2026_11_0_form_list_view_address()` (2026.11.0) reads each column type with `install_column_info()`, appends `address_name` with `install_modify_column()` keeping every existing value (`random` in `order_by_1`), the null rule and the default; a second run skips all four. Existing rows do not change.
- `edit_form_list_view.php` checks the three order-by fields and every filter row with an operator before anything is written; a value outside the list shows an error and returns to the screen instead of saving `''` (the session is not strict, so MySQL stored any unknown ENUM value as empty).
