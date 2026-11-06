---
name: comment-attachment-upload-refusal
description: The comment form's attachment (add_comment.php) refuses a file PHP did not take in whole before the comment is written, and writes a files row only after a successful copy.
---

## What happened before?
- A comment attachment over `upload_max_filesize` (or cut short) arrives with its name and an empty `tmp_name`. `add_comment.php` checked only the name, so `copy('')` threw on PHP 8 (an error page, the comment lost) or, on PHP 7, failed quietly and a `files` row of size 0 pointed at a file that never arrived.

## What does it do now?
- Right after the CAPTCHA check: `pg_upload_file_refusal($_FILES['file'], pg_upload_limits()['file_max'])` marks the `file` field, so the visitor goes back to the form with the reason and the limit; the name and review photo checks skip a field already marked.
- The copy is `if (!@copy(...))`: a failure is logged and ends the request before the `files` row.

## What still lacks it?
- The designed form handler `custom_form.php` copies an upload without reading its error code; its fix follows separately.

## Tests?
- `tests/comment_upload_refusal_test.php`.

Related: docs/memory/platform/upload-limits-and-empty-post.md
