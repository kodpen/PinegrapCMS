---
name: security-scan-file-scan
description: Security Scan file scan - the five file rules, what each reads, the release reference it judges by, the resumable walk, the server-config baseline, the job and its state row.
module: security_scan_files
---

## Which rules does the file scan have?
- `file_php_unknown` (high): a PHP file the release does not carry. Root files and `includes/` are judged by the integrity reference; elsewhere only `pg_security_php_known_outside_reference()` passes (the shipped list plus the language cache `data/temp/lang_<code>.php`).
- `file_upload_executable` (critical): a PHP extension in any segment of a name under `data/files/` (`shell.php.jpg` counts), or in `data/layouts/` other than `<page id>.php`.
- `file_picture_php` (high): `<?php` followed by a space or the end inside a picture in the upload folders. `<?=` is not looked for: it turns up by chance in large compressed pictures.
- `file_server_config` (medium, high in the upload folders): a `.htaccess` / `.user.ini` new or changed against the baseline; detail `new` or `changed`.
- `file_shell_mark` (critical): `pg_security_shell_marks()` (`eval(base64_decode(`, `assert($_`, ...) in a PHP file outside the reference only; files in the reference are never read for it.

## Which reference does it judge by?
- `pg_security_files_reference()` reads only what the integrity check keeps (`core/file-integrity-check.md`): the dev reference stamped for this host and version, else the cached GitHub tree of this version. It never fetches.
- No reference: root and `includes/` PHP files are not judged, and a finished run does not mark `file_php_unknown` or `file_shell_mark` gone (`pg_security_files_judged_kinds()`).
- A new PHP file the release ships outside root and `includes/` must be added to `pg_security_php_shipped_outside_reference()`; `tests/security_scan_files_test.php` fails until it is.

## How does the walk run and resume?
- Roots in order: the software folder, `FILE_DIRECTORY_PATH` as `data/files`, `LAYOUT_DIRECTORY_PATH` as `data/layouts` (`pg_security_files_roots()`; `pg_security_place_disk_path()` maps a place back to disk).
- Skipped: `.git`, `data/backups`, `data/temp/restore_<id>`, and the upload folders inside the software folder (walked as their own roots). Symbolic links are not followed. A quarantine folder (SC3) must be added to `pg_security_walk_skipped()`.
- Sorted walk; the cursor is the last place done, compared segment by segment (`pg_security_place_compare()`, `pg_security_folder_done()`), so a resumed tick skips folders already done.
- `security_scan_job.php`, catalogue `security_scan_job`: heavy lane, daily, lock 600 s, budget `SEO_ANALYZE_TIME_BUDGET` capped by `pg_cron_time_left()`; unfinished, `pg_cron_due_again()`; finished, `pg_cron_ran()`. A web request needs a signed-in Manager.
- Content is read in 1 MB chunks with a 512-byte carry, at most 32 MB per file.

## Where is the run kept, and what does a finished run do?
- One `config_text` row `security_scan_files` (JSON): started, root, cursor, counts, reference source, baseline, the run's config digests, last_finished, last_files, last_reference.
- Every sighting is merged with `pg_security_finding_merge()` and stamps `last_seen`; at the end, rows of the judged kinds still open or ignored with `last_seen` before the run's start become gone, and gone rows past 90 days are deleted.

## How does the server-config baseline work?
- The first finished run takes every `.htaccess` / `.user.ini` as it is (no findings). Later runs only drop files that disappeared: a new or changed file stays reported until a person ignores it. A rewrite by this software (server rules repair) is reported too; SC8 decides whether it needs an exception.
