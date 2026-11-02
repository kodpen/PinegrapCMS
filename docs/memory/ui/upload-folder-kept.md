---
name: upload-folder-kept
description: Edit Field keeps a deleted or unlisted upload folder selected and marked; the File Manager's folder delete names the upload fields using the folder
---

## What was the symptom?
- A File Upload or Signature field's `form_fields.upload_folder_id` outlives its folder (a folder deleted in the File Manager, a Recycle Bin purge), and `select_folder()` leaves out a folder in the bin. Edit Field's select had no option for it and no blank first one, so the first folder showed as selected and a plain Save wrote it. Uploads meanwhile land with `files.folder` = the gone id (`custom_form.php`), which the File Manager hides (`folder_archived` filter on a LEFT JOIN).

## What does Edit Field do now?
- `pg_field_form_kept_folder()` (includes/forms/field_form.php, pure) appends the stored id selected, marked "(deleted folder)" when no folder row is left (`pg_field_form_folder_name()` is null), else its name plus "(no longer offered)", with a `form-text` line `#upload_folder_id_help` (the select's `aria-describedby`) asking for another folder. The name is read only when the list left the id out. Same pattern as `pg_pb_kept_option()` (`stored-choice-kept.md`).
- Saving the kept id stays allowed: `edit_field.php` always accepts the old upload folder. Stored rows are never rewritten; `select_folder()` is unchanged.

## What does the folder delete say?
- The File Manager's delete check (`pg_explorer_handle_delete_check()`, `includes/explorer/handle_recycle.php`) counts `form_fields` of type file upload or signature whose `upload_folder_id` is the folder or a folder inside it (`upload_fields`); above 0 the confirmation says that later uploads to such a field will not show in the File Manager (#4889; the retired Edit Folder screen said it for the folder alone).

## Where are the tests?
- `tests/upload_folder_kept_test.php`; query counts in `db_q_contacts_forms_test.php` (field_form.php).
