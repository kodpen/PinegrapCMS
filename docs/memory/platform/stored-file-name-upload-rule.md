---
name: stored-file-name-upload-rule
description: prepare_file_name() applies the upload rule (pg_upload_neutralize_name()) after the 100-character cut, so a cut never leaves a refused extension such as .php
module: files
---

## What does prepare_file_name() do, in order?
- Trim, `.htaccess` to `file`, `pg_ascii_file_name()`, `file` in front of a name that starts with a dot, the 100-character cut, the space/`&`/`+`/`#` replacement, the trailing-dot trim, then `pg_upload_neutralize_name()`, then the dkim.key check (`dkim-key-in-file-manager.md`).
- `pg_upload_neutralize_name()` turns the dot in front of every refused extension into `_` (`shell.php.jpg` is stored as `shell_php.jpg`); it does not change the length, so it can run after the cut.

## Why does the upload rule run after the cut?
- The cut keeps a short last extension (under 6 characters) and cuts the part before it, or cuts the whole name at 100 when the last extension is long. Before #4613 the rule ran before the cut, so a cut could create a refused extension the rule never saw: `aaa…a.php123456` (96 a's) was stored as `aaa…a.php`, and `aaa…a.phpXXXX.jpg` (92 a's) as `aaa…a.php.jpg`.
- The doors ask `pg_upload_name_blocked()` on the name as it was sent, which passes such names, so the stored file kept a `.php` extension in the files folder. Now it is stored as `…_php` / `…_php.jpg`.

Pinned by `tests/file_names_test.php`. Related: docs/memory/platform/file-name-last-segment.md
