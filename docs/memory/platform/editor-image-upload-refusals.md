---
name: editor-image-upload-refusals
description: The rich text editor's Image > Upload (editor_add_file.php): a discarded body, a missing file, an upload error code or a failed copy() is refused in the dialog, never inserted as a picture.
---

## How does editor_add_file.php answer a refusal?
- Through `callFunction(funcNum, '', message)`, which CKEditor's Image dialog shows as a failure; a URL with an empty message is success.
- `editor_add_file_refuse()` logs the message to the Site Log, passes it through `escape_javascript()` and ends the script.

## What is refused, and in which order?
- First the token and login checks: the token is in the query string, so a body PHP discarded still passes them.
- Then, before `$_FILES` is read: `pg_post_body_discarded()` (message names post_max_size), a missing file or `UPLOAD_ERR_NO_FILE` ('Please select a file.'), any other error code through `pg_upload_file_refusal()`.
- The files row, the 'was created' log line and the success answer follow only a `copy()` that returned true; else 'The server could not accept {var:1}. Please try again.'

## What did it fix?
- A 6 MB phone photo on a host with upload_max_filesize 2M arrived with error 1 and an empty tmp_name: PHP 7 recorded a 0-byte row whose link 404s and inserted a broken picture; PHP 8 threw ValueError on `copy('')` and the hidden frame got a bare 500. A full disk gave the same false success. Pinned by `tests/editor_add_file_upload_test.php`.

Related: docs/memory/platform/upload-limits-and-empty-post.md
