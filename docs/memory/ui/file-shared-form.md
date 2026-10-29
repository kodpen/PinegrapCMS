---
name: file-shared-form
description: add_file.php and edit_file.php draw the File Access Control and Description cards through includes/forms/design_file_form.php; what each screen keeps
---

## What is shared?
- The two cards of `includes/forms/design_file_form.php` (`ui/design-file-shared-form.md`), loaded with `require_once` after `validate_area_access()`. Done in #2971.
- Upload: `pg_design_file_form_access_card(select_folder($folder_id, 0), array('design' => ($user['role'] <= 1), 'design_checked' => false))` and `pg_design_file_form_description_card()`.
- Edit: the same card with `select_folder($row['folder'])` and `'design_checked' => ($design == 1)`, and `pg_design_file_form_description_card($row['description'])`.
- The Design File switch shows for role ≤ 1 only; `design_checked` (default true for the design file screens) leaves it off for a new upload and follows the stored flag on edit.

## What stays in the screens?
- Upload: the dropzone script and form, the Files To Upload card, the limits, the INSERT loop, the JSON answer, the notices.
- Edit: the id not-found gate, the row load, the File Name field and rename guards, image and editor rows, convert rows, replace, delete, the UPDATE, the button bar, the `md:col-*` wrappers.
- Every query stays in the screens (`db_q_*_test.php` count them; `panel_id_gate_test.php` and `panel_missing_param_test.php` read the gates).

## Which test covers it?
- `tests/file_form_test.php`: the checked, unchecked and absent Design File switch, both screens calling the cards and drawing no folder, design or description field themselves.
