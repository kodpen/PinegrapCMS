---
name: custom-form-page-copy
description: What Duplicate Page copies of a form: every column of the fields, choices and pick list targets, a field once, a designed form's settings and its field-id settings moved to the copy (#11453)
module: custom_form
---

## Which fields does a duplicated page get?
- A classic form page (custom form, shipping, billing, express order) copies its rows through `pg_cf_copy_page_fields()` (`includes/fn/custom_form.php`): every column of `form_fields` (quiz answer, RSS role, pattern, rules, translation switch), of `form_field_options` (administrator addresses, upload folder, pick list target moved to the copy's field) and the `target_options` of its choices (`pg_cf_copy_row()`, so a column a later step adds is copied too).
- Before #11453 a column list of its own dropped the quiz answers, RSS roles, patterns, option addresses and targets.
- A field whose name the copy already has is not copied again: a designed widget on the page wrote it in the first sync. A W22b page (custom form type, widget beside the classic layout) got every field twice.

## What does a designed form keep?
- A page of another type whose copied tree has an owning form widget gets the original's `custom_form_pages` row first (`pg_cf_copy_form_settings()`), named `<name>[n]` like a classic copy, so the first sync keeps it. It used to start with the defaults: no notification e-mail, the default confirmation, no fee.
- Then `pg_cf_copy_form_field_settings()` moves `schema_author` (`field:<id>`) and `share_image_field_id` to the copy's field of the same name (`pg_cf_copied_field_settings()`), else contact and none.
- A widget set to "existing form" of another page owns no form on the copy: nothing is copied.

## Tests?
- `tests/custom_form_copy_test.php`. No database here: the copy itself was not run.
