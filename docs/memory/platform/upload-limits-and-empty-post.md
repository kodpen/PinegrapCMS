---
name: upload-limits-and-empty-post
description: Why an empty $_POST is an error (post_max_size), pg_post_body_discarded(), pg_upload_limits() three ceilings, batching uploads, checking $_FILES error codes.
---

## Why is an empty $_POST an error?
- When `post_max_size` is exceeded PHP discards the body before the script runs: `$_POST` and `$_FILES` are empty; the only trace is a warning in the error log.
- A script that treats this as "no POST yet" redraws the form and returns 200, which the browser reads as success. Symptom: "I choose the file, it says uploaded, nothing uploads".
- `pg_post_body_discarded()` (`includes/fn/files.php`) catches it via `Content-Length`; in `add_file.php` it is the first step after `init.php`. Every new script that receives a form POST must have this check right after `init.php`.
- Never in an endpoint whose body is JSON (`api.php` and the like): PHP does not fill `$_POST` from JSON, so the check fires on every request and even a small PNG got 413. Read `php://input` there instead.

## What two rules follow?
1. A flag that requests JSON cannot live in the body: `jsonreturn` did, and the request that loses its body is the one that most needs a JSON answer. Put it in the query string (`add_file.php?jsonreturn=true`); `X-Requested-With` is the fallback; the response is **413**.
2. `ini_get()` returns strings: comparing a size with `"8M"` compares with `8`. Always use `pg_ini_bytes()` (`includes/image_engine.php`, so `get_file.php` has it too).

## What are the three ceilings in pg_upload_limits()?
- `post_max_size`: body dropped entirely, script sees nothing.
- `upload_max_filesize`: file arrives with an error code + empty `tmp_name`.
- `max_file_uploads`: extra files are dropped WITHOUT any error code.
- `request_max` = `post_max_size` − 64 KB (multipart envelope, field names, token are inside the body too).

## How should uploads stay under the limits?
- Staying under the limit beats raising it (the limit is the server's; operators often cannot change it).
- Multi-file uploads are split into batches sent sequentially (`uploadBatches()` / `sendBatch()` in `assets/js/product_builder.js`; a new multi-file uploader follows them); parallel sending puts the same bytes on the wire at once. One batch failing must not drop later ones: `sendBatch()` never rejects.
- Exception: the Workspace notes import sends one request on purpose, since a stage is all-or-nothing; its cap counts request_max (`workspace/workspace-notes-import-upload.md`).
- A single file above `upload_max_filesize` is NOT sent.
- Upload screens and the Settings screen state the limits.

## Why must `$_FILES[...]['error']` be read before creating a record?
- An over-limit file arrives with empty `tmp_name`; `copy()` fails, and if its return is ignored the `INSERT` still runs → a file row whose link 404s and whose optimize button says "diskte yok".
- `UPLOAD_ERR_NO_FILE` is skipped silently (not an error, just the form's empty row); every other code is reported to the operator.

Related: docs/memory/platform/browser-image-preshrink.md
