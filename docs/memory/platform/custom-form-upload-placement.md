---
name: custom-form-upload-placement
description: Custom form file uploads (custom_form.php): an upload error code is refused at validation; every file is copied and given its files row before the forms row, and a failed copy sends the form back.
module: custom_form
---

## How does custom_form.php store an uploaded file?
- At validation, a file upload field whose `$_FILES` entry has an error code is marked with `pg_upload_file_refusal()` (limit from `pg_upload_limits()`), so the form comes back.
- After the hook code and before `INSERT INTO forms`, each file upload field with a file is copied through `pg_cf_place_upload()` (`includes/fn/custom_form.php`) and gets its files row; `$placed_uploads[field id]` keeps the id and name, and the store loop writes that id into form_data.
- A copy that fails marks the field 'The server could not accept {var:1}. Please try again.' and `pg_cf_remove_placed_uploads()` deletes the files and rows placed before it.

## Which upload fields can a visitor's post fill?
- Only those drawn for them. `pg_cf_undrawn_upload_ids()` names an office use only upload field outside staff's edit mode and a field the form dropped (the SMS question); `custom_form.php` empties their `$_FILES` entry before validation, as it does for a field "Shown when" hid.
- Before (fixed in the form submissions bug hunt), the validation skipped such a field but the placing loop walked every field: a file posted for it was copied into the files folder without the blocked-name check (`pg_upload_name_blocked()`), PHP's error code or the size limit, and attached to the record.

## What did it fix?
- The copy() result was ignored and no error code was read: a full disk or unwritable files folder stored the submission with a files row pointing at nothing; a file over upload_max_filesize gave a 0-byte row on PHP 7 and a ValueError on `copy('')` on PHP 8 after the forms row and earlier answers were written (a half-saved submission). Pinned by `tests/custom_form_upload_place_test.php`.

Related: [[form-upload-beside-fields]], [[editor-image-upload-refusals]]
