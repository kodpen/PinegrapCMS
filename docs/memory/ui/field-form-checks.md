---
name: field-form-checks
description: What the form field screens refuse on save (type, number boxes, blank choices) and which pages the form view and field screens open for
---

## What does a field save refuse?
- A type the form does not offer: `pg_field_form_validate_type()` checks the post against `pg_field_form_types($form_type, $signature_ready)` (the list of `select_field_type()`: file upload and signature on a custom form only, signature only with its column). The `type` column is an ENUM and the session is not strict, so any other value was stored as an empty type. The type select is `required`, so the browser names it before the Name box hidden in the collapsed settings card ("not focusable" was all it said).
- Size, Maximum Characters, Rows, Columns: a whole number from 0 to `pg_field_form_number_limits()` (1000, 65535, 1000, 1000), empty is 0 (`pg_field_form_number()`). Only a box the type shows (`pg_field_form_number_boxes()`, the same rows as `change_field_type()` in `backend.src.js`) is marked; a hidden one is saved as 0. The unsigned INT columns used to clip -5 to 0 and round 12,50 to 13 without a word. Rows and Columns use the `integer` mask.
- Tested in `tests/field_form_test.php`.

## Which choice lines are saved?
- `pg_field_form_option_lines()`: a line with neither label nor value (blank, a lone `|`) is no choice; an empty value needs a label (`Select…|`). Blank lines used to become unnamed choices and unlabelled radio buttons.
- Edit Field deletes the stored choices and their `target_options` for every type, so a field turned into a text box keeps none.

## Which pages do the screens open for?
- `edit_form_list_view.php` and `edit_form_item_view.php` stop with "page not found" (404) unless the page type is `form list view` / `form item view`: another page has no view row, printed ~30 warnings and a save wrote a view row for it. A view with no row yet starts empty.
- `view_fields.php` calls `pg_form_fields_refuse_page()` for a page with no form, as `add_field.php` and `delete_fields.php` do.
