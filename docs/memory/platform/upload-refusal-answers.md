---
name: upload-refusal-answers
description: Upload Design Files (add_design_file.php) answers a Dropzone refusal in JSON with a 4xx status and shows it in a role=alert line; why an HTML 200 refusal looked like success
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

## What is still open?
- `add_file.php` already has the discarded-body block but not the JSON refusals, `dictResponseError` or the visible line; it was held by another PR when this landed.

Pinned by `tests/upload_refusal_answers_test.php`. Related: docs/memory/platform/upload-limits-and-empty-post.md, docs/memory/ui/upload-keyboard.md
