---
name: upload-folder-kept
description: Edit Field keeps a deleted or unlisted upload folder selected and marked; Edit Folder's Delete names the upload fields using the folder
---

## What was the symptom?
- A File Upload or Signature field's `form_fields.upload_folder_id` outlives its folder (Edit Folder's Delete of an empty folder, a Recycle Bin purge), and `select_folder()` leaves out a folder in the bin. Edit Field's select had no option for it and no blank first one, so the first folder showed as selected and a plain Save wrote it. Uploads meanwhile land with `files.folder` = the gone id (`custom_form.php`), which All Files and the File Manager hide (`folder_archived` filter on a LEFT JOIN).

## What does Edit Field do now?
- `pg_field_form_kept_folder()` (includes/forms/field_form.php, pure) appends the stored id selected, marked "(deleted folder)" when no folder row is left (`pg_field_form_folder_name()` is null), else its name plus "(no longer offered)", with a `form-text` line `#upload_folder_id_help` (the select's `aria-describedby`) asking for another folder. The name is read only when the list left the id out. Same pattern as `pg_pb_kept_option()` (`stored-choice-kept.md`).
- Saving the kept id stays allowed: `edit_field.php` always accepts the old upload folder. Stored rows are never rewritten; `select_folder()` is unchanged.

## What does Edit Folder's Delete say?
- It counts `form_fields` of type file upload or signature whose `upload_folder_id` is the folder and, above 0, the confirmation is one `{suffix:1}` sentence naming that count and that later uploads will not show in All Files or the File Manager. What the delete removes is unchanged; the confirmation is now escaped with `h()`.

## Where are the tests?
- `tests/upload_folder_kept_test.php`; query counts in `db_q_content_screens_test.php` (edit_folder.php) and `db_q_contacts_forms_test.php` (field_form.php).
