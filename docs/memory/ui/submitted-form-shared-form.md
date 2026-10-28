---
name: submitted-form-shared-form
description: The submitted form create and edit screens share includes/forms/submitted_form_form.php
---

## Where are the submitted form fields?
- `includes/forms/submitted_form_form.php` (gate `PG_FUNCTIONS_DIR`). `add_submitted_form.php` and `edit_submitted_form.php` stay at the root and `require_once` it.
- `pg_submitted_form_fields_markup($liveform, $user, $page_id, $office_use_only_access, $form_id)` returns `fields`, `wysiwyg_javascript` and `enctype` of the "Form Data" card. `$form_id` `''` = new: signature pad (and its script), plain upload with the required note, no hidden check box field. An id = existing: signature shown with its seal (never redrawn), stored file with Replace and Delete File, a hidden field before each check box group (the save replaces only fields found in the post).
- `pg_submitted_form_check_field($liveform, $field, $page_id, $pretty_urls, $form_id)` marks blocked upload names, invalid date / date and time / e-mail / time, and a title whose address name another form of the same custom form uses (`$form_id` is excluded).
- `pg_submitted_form_store_upload($field, $user_id)` copies the upload under a free name and returns the `files.id`; `pg_submitted_form_save_value($liveform, $form_id, $field)` writes the `form_data` rows (one per value; date types keep their type, rich text is `html`).

## What stays in the screens?
- Create: the custom form pick list, the required checks (only on Complete), the signature check and store, the `forms` insert, notifications, redirects.
- Edit: access (also posts from a site's form item view page, its hook code), prefill, form properties card, the required checks (only fields that appeared in the post), replacing / deleting a stored file, delete, notifications on completion, `send_to` redirects.

## Why only these parts?
- PR #2466. Measured among the remaining add/edit pairs: add 1,048 and edit 1,511 lines, about 706 shared, the largest free pair. The save flows differ (edit is also posted from the site), so only the identical parts moved. Tests: `tests/submitted_form_form_test.php`.

## What if a submission has no row for a file field?
- `db_item()` returns null there, so the markup, the replace path and the delete path of the edit screen read `$file` only when a row came back. Symptom fixed: `E_WARNING "Trying to access array offset on null"` on every opening of a submission whose upload field was left empty (#2065).
