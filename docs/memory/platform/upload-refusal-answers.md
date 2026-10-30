---
name: upload-refusal-answers
description: Upload Design Files and Upload File answer a Dropzone refusal in JSON with a 4xx status and show it in a role=alert line; per-file upload error codes on design uploads
---

## Why did a refused design upload look like it worked?
- Dropzone posts with `X-Requested-With: XMLHttpRequest` and counts any 2xx answer as success; `successmultiple` goes to `view_design_files.php` (or `send_to`) without reading the answer.
- `output_error()` and `validate_token_field()` print an HTML page with status 200, so a refused name (`.php`, `.htaccess` …), an expired token or an empty batch landed on the list with no notice and no file. A batch over `post_max_size` reached the `if (!$_POST)` form branch and got the form again with 200.
- A 5xx showed Dropzone's English `Server responded with … code.` or the raw HTML text in the file bubbles; `errormultiple` only wrote to the console.

## What does the screen answer now?
- `$upload_wants_json` is true for the Dropzone XHR. On that path `$upload_refusal_answer($status, $message)` answers JSON `{status: "error", message, error}` and exits (`error` is the key Dropzone writes into each file bubble):
  - discarded body (`pg_post_body_discarded()`, before the form branch): 413 with add_file.php's "Nothing was uploaded …" sentence;
  - token mismatch: 403, "Your session expired. Reload the page and try again." (the same comparison as `validate_token_field()`);
  - empty batch: 400 "Please select a file."; refused name: 415 `pg_upload_blocked_message()`.
- The fallback form (no Dropzone) keeps `validate_token_field()` and the `output_error()` pages. The server refuses exactly what it refused before.
- The Dropzone options set a translated `dictResponseError`. `errormultiple` shows `response.message`, a plain string answer, "The server answered N without a readable error message." for an HTML body, or the failure sentence, in `#pg_upload_error` (`role="alert"`, above the form), and writes the same text into each file bubble. `sendingmultiple` hides the line again.

## What does Upload File answer?
- The same helper and line (#3666): the discarded-body 413, the token 403, the empty batch 400 and the folder refusal 403 `Access denied.` answer JSON on the XHR path (`jsonreturn` or `X-Requested-With`); the fallback form keeps the HTML pages. The 413 body now has the `error` key (Dropzone showed `[object Object]` without it).
- A Dropzone batch where every file failed answers 400 with the failed sentences instead of landing on All My Files. `jsonreturn` callers (product photos, cloud picker) still get `status: success` with the `failed` list, since they return before that point; they already read `message` from a 4xx body.

## How does Upload Design Files treat a file over the limit?
- A file over `upload_max_filesize` (under `post_max_size`) arrives with an error code and an empty `tmp_name`. Before #3666 nothing read the code: on PHP 8 `copy('')` threw ValueError (bare 500 mid-batch, the earlier files saved, the rest lost); on PHP 7 `copy()` failed unchecked and a size-0 `design=1` row was written, with "has been uploaded" and a 404 link.
- Now each file's code is read before any copy, with add_file.php's sentences (larger than / arrived in part / could not accept); a failed `copy()` writes no row. Failed files become warnings on All Design Files; the notice counts only the written files; a batch with nothing written answers 400 (XHR) or an error page (fallback).
- The screen takes add_file.php's `maxFilesize` (server limit), `dictFileTooBig`, the `accept()` queue-total check and the "At most … per file, and … in one upload." line. It still writes its own INSERT (not `pg_file_store_upload()`), so design uploads keep their log line and announce no `file.created` event.

Pinned by `tests/upload_refusal_answers_test.php`. Related: docs/memory/platform/upload-limits-and-empty-post.md, docs/memory/ui/upload-keyboard.md
