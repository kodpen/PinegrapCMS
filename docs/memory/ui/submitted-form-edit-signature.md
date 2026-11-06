---
name: submitted-form-edit-signature
description: Saving an existing submitted form never rewrites a signature field's form_data row
---

## Which fields does an edit save write?
- `edit_submitted_form.php` asks `pg_submitted_form_edit_writes_field($field)` (`includes/forms/submitted_form_form.php`) for each field: a signature or information field is passed over, every other type goes through the office-use rule and is saved.

## Why is a signature passed over?
- A signature is written once, at create time: its `form_data` row points at the drawn PNG (`file_id`) and `form_signatures` seals it. No edit form prints it (panel: shown with its seal; site form item view: not printed), so a value posted under its id is never an answer.
- Before the guard, a hand-made post with the field id (from a submitter allowed to edit on the site, or a role-3 forms manager) deleted that row and stored the posted text: the file lost its link (File Manager usage, the PNG looked unused) and the receipt's document read the text back.
- Test: `tests/submitted_form_edit_signature_test.php`.
