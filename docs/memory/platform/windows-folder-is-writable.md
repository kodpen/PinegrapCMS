---
name: windows-folder-is-writable
description: Ask pg_dir_writable(), never is_writable(), about a folder: PHP on Windows reads its read-only attribute (OneDrive, Explorer), which stops nothing; where it bit, how each check asks now
---

## Why never gate a write on is_writable() of a folder?
- PHP on Windows answers `is_writable()` of a folder from its read-only attribute first (php-src win32/ioutil.c), then from its ACL for PHP's user (`tsrm_win32_access()`), so a folder can read as closed for either reason. Windows does not enforce that attribute on folders; Explorer sets it on a customised folder and OneDrive on folders it decorates, so a site's folder can carry it.
- Either ask `pg_dir_writable()`, or make the folder when it is missing, ask `is_dir()` and let the write answer, logging a failure with what PHP said (`error_get_last()`). On Linux `is_writable()` is the truth.
- A file's read-only attribute is enforced, so `is_writable()` of a file is a fair question.

## What does pg_dir_writable() answer?
- In `includes/image_engine.php`, not `includes/fn/files.php`: `get_file.php` makes derivatives and loads that file, never functions.php (core/get-file-shared-code.md).
- No folder: false. `is_writable()` yes: true. Otherwise on Linux false, nothing written; on Windows it makes a file with `tempnam()`, removes it, and answers true only when the file's parent is that folder (`pg_dir_probe_landed_in()`, an exact match, not a prefix): `tempnam()` makes its file directly in the folder it used and falls back to the system temp folder, which can sit below the asked one (sys_temp_dir or TMP inside the site). It names the file under the folder's real path, so `realpath()` of the folder is compared too (the installer asks of `install/../data`). `tempnam()` disabled: false.
- It keeps nothing between calls (System Status scans again after its repair); a hot path keeps the answer itself.
- `$windows` and `$make_file` exist for tests/folder_writable_test.php: a stream wrapper whose `url_stat` reports mode 040555 is a folder `is_writable()` calls closed whoever runs the tests (root passes any chmod).

## Where did it bite, and how is each folder asked now?
- Workspace notes import: every upload answered "The file could not be saved." on the dev site (Windows, in a OneDrive folder; PR #1497, issue #1469); `ws_note_import_temp_dir()` asks `is_dir()` only (workspace/workspace-notes-import.md).
- ERP document archive: no folder check, the write answers (erp/erp-document-archive.md).
- `pg_dir_writable()` since PR #1557, each with the message it had: `erp_import_temp_dir()` ("The file could not be read." on every accounts CSV upload), `erp_export_temp_path()` ("Check that data/temp is writable."), the update's pre-check for new files in `pg_extract_archive()` (kept, so a package never half-applies), the web root folder when the server file is missing (`pg_server_config_scan()`), the installer's data folder line, System Status' write permission scan, and the image engine: `pg_image_derivative()` (per file folder, kept per request; it sent the original on every request) and `pg_image_derivative_formats()` (formats.json was never kept).
- Left as they are: the vendored dompdf and php-svg-lib font cache checks, and `pg_write_permission_repair()`'s `is_writable()` after its `chmod()`: PHP's `chmod()` on Windows sets or clears the read-only attribute, so only the ACL is left to read, and the scan it runs next asks `pg_dir_writable()`.

## How sure is the notes import cause, and how is it confirmed?
- Found by reading the code and php-src and reproduced with a Linux emulation, not seen on the site: `is_writable()` of data/temp was the only refusal before anything was written.
- `attrib` showing R on data/temp confirms the read-only attribute. If uploads still fail, the log names the step and PHP's message; no line at all means PHP cannot write in data/temp, where php_errors.log lives (check its ACL with `icacls`).
