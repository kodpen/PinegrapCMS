---
name: form-names-distinct
description: A form field name is unique within its form (Create / Edit Field refuse a taken one); the custom form pickers of Forms and Add Submitted Form tell same-named forms apart and mark binned ones
---

## Why must a field name be unique in its form?
- The export headings, the import mapping (`import_submitted_forms.php`, first row by `page_id` + `name`), the API value keys, the `^^name^^` merge field and the "required when" rule (`pg_cf_rule_other_field()`) all find a field by its name. A second field with the same name lost its data on each path: the export wrote two equal headings and the import put both columns into the first field (#4032).

## How is it refused?
- `pg_field_form_validate_name($liveform, $name, $names_in_use, $stored_name)` (`includes/forms/field_form.php`) marks "Another field of this form already has this name…". `pg_field_form_names_in_use($form_type_filter, $exclude_id)` reads the other fields of the same form through `pg_field_form_filter()` (page, product, variant set template; express order per form type).
- `pg_field_form_name_taken()` compares without case (`mb_strtolower`), as `pg_cf_rule_other_field()` does. Edit passes the stored name: a field saved again under its own name is not refused, so a form that already holds two equal names keeps saving. Nothing renames existing duplicates and there is no unique index (that would be a schema step).
- The designer needs no check: its server sync matches fields by name and `_cfEnsureNames()` renames a copy (`designer/custom-form-sync.md`).
- Tested in `tests/field_form_test.php`.

## How do the custom form pickers tell forms apart?
- A changed design or loaded template leaves older forms with the same name, their pages in the Recycle Bin. `pg_submitted_form_list_custom_forms()` (`includes/fn/submitted_form_list.php`) now answers `id`, `name`, `binned` through `pg_submitted_form_list_labels()`: a form name that appears more than once gets ` — <page name>` (a binned page's original name, `pg_recycle_parked_original()`), a binned form gets ` [IN RECYCLE BIN]` and goes after the live ones. Binned forms stay in the list: their submissions are still there.
- Users: the Forms screen's Custom Form filter and the advanced filters' boxes (`view_submitted_forms.php`), and Add Submitted Form's picker, which used to key its options by form name so same-named forms collapsed into one, sometimes the binned one. It now uses the same list (a label still equal gets ` (#id)`).
- Tested in `tests/submitted_form_list_test.php`. #4034.
