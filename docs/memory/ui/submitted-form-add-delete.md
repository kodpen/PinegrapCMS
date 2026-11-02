---
name: submitted-form-add-delete
description: Add Submitted Form reads the pressed button from the request, its e-mail box, Delete Selected skipping missing forms, and the field filter labels of the Forms screen
---

## Why does Add Submitted Form read its buttons from `$_POST`?
- The screen draws its validation errors with `unmark_errors()`, not `remove_form()`, so the posted values come back. That writes `error` keys into every session entry, the button pressed before included, and `add_fields_to_session()` only clears `value`; `field_in_session()` tests the entry, not its value, so the old button still counted as pressed.
- After "Save for Later" failed, "Save and Complete" stored the record as incomplete, said "saved for later" and sent no notifications; the other order enforced required fields on "Save for Later" (#5588).
- `add_submitted_form.php` now sets `$save_for_later_clicked` / `$complete_clicked` from `isset($_POST[...])`. Edit Submitted Form calls `remove_form()` on its GET and was not affected.
- The same trap holds for any liveform screen that redraws errors with `unmark_errors()` and asks the session which submit button was pressed.

## How is an e-mail address field drawn on the add and edit screens?
- `pg_submitted_form_email_attrs($type)` (`includes/forms/submitted_form_form.php`) gives an 'email address' field `type="email"`, `inputmode="email"`, `autocomplete="email"`, `spellcheck="false"`, `autocapitalize="off"`; the server check (`validate_email_address()`) stays (#5595).

## What does Delete Selected do with a form that is already gone?
- `delete_submitted_forms.php` passes over an id whose form is not found and does not count it: `check_edit_access()` of a null folder is true for a manager, so a stale list said "1 form(s) have been deleted." and logged it. No deletion at all marks "No forms were deleted"; a missing or non-array `forms` is "none were selected" without a PHP warning (#5591).

## What do the field filter boxes of the Forms screen show?
- The advanced filters' custom form field boxes are labelled by `pg_submitted_form_list_field_label($label, $name)`: the field's label as the add screen shows it (markup taken off), its name when it has none, cut at 60 characters with the whole label and the name as `title` (#5593).
- Tested in `tests/submitted_form_list_test.php` and `tests/submitted_form_form_test.php`.
