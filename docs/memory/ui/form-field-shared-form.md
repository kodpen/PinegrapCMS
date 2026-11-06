---
name: form-field-shared-form
description: The form field create and edit screens share includes/forms/field_form.php: wide layout with the type first, a Summary (form, type, name, Last Modified), what stays in the screens
---

## What is shared?
- `includes/forms/field_form.php`: `pg_field_form_cards($liveform, $field, $context)` draws the form column and the sticky status column; `pg_field_form_layout($cards, $buttons)` adds the row and the sticky save bar; `pg_field_form_status_row($label, $value, $last)` is one Summary row. `$context` adds `form_label` (the form, HTML, first Summary row) and `status_rows` (more rows, HTML) to the older keys. The checks, the option parser and the save helpers were already there (`tests/field_form_test.php`).
- A screen keeps its gates, the form type branches (page, product group, product), the hidden ids, the buttons (edit adds Delete), the INSERT / UPDATE, the log line and the redirect. The designer form's read-only view of `edit_field.php` is not part of it.

## How is the screen laid out?
- The product_builder pattern (`ui/edit-screen-wide-layout.md`, B11, PR #6719). Form column: the Field Type card first, then `#options_field_row` (shown once a type is picked, `collapse-if-selected`) with the Field Settings, Information and Value Options cards. Status column: Summary of the form (`Custom Form: name`, or the edit screen's page / product line, which left the line above the form), the type and the name, both following the fields; edit adds Last Modified (`form_fields.timestamp` and `user`).
- The row ids that `change_field_type()` in `backend.src.js` shows per type are unchanged, so the script was not touched.
- Edit's Delete is `formnovalidate`: an emptied Name box or no type no longer blocks it in the browser.
