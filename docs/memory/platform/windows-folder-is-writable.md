---
name: windows-folder-is-writable
description: Never gate a write on is_writable() of a folder: PHP on Windows reads the folder's read-only attribute (OneDrive, Explorer), which stops nothing; where it bit and which gates are left
---

## Why never gate a write on is_writable() of a folder?
- PHP on Windows answers `is_writable()` from the file attributes: a folder with the read-only attribute is "not writable" (php-src win32/ioutil.c). Windows does not enforce that attribute on folders; Explorer sets it for a customised view and OneDrive on synced folders, so a dev site in OneDrive has it everywhere.
- Make the folder when it is missing, ask `is_dir()`, then write: the write's result is the answer, and a failure is logged with what PHP said (`error_get_last()`). On Linux `is_writable()` is the truth, but the write answers there too.
- A file's read-only attribute is enforced, so `is_writable()` of a file is a fair question.

## Where did it bite, and how is it handled?
- Workspace notes import: every upload answered "The file could not be saved." (issue #1469); `ws_note_import_temp_dir()` asks `is_dir()` only (workspace/workspace-notes-import.md). The test emulates such a folder with a stream wrapper whose `url_stat` reports mode 040555, since root passes any chmod.
- ERP document archive: no `is_writable()`, the write answers (erp/erp-document-archive.md).
- System Status folder checks: `is_writable()`, and on Windows a `tempnam()` probe when it says no (`$folder_writable` in includes/fn/system_status.php).

## Which folder gates are still there?
- `erp_import_temp_dir()` (includes/erp/import.php), the ERP export's temp folder (includes/erp/export.php), the image engine's derivative folders (includes/image_engine.php), the update's folder probe (includes/fn/update.php), the web root folder in includes/server_config.php, the installer's data folder line. Each says no for such a Windows folder; change one the same way when it is touched or reported.
