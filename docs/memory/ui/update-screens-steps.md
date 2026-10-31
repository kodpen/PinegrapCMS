---
name: update-screens-steps
description: Software Updater, Repair Software and Backup Manager: the step loop with an error path, the status line, Return in Backup Name, labels kept while they work, a failed file copy removes the backup
---

## How do the update and repair screens run their steps?
- `software_update.php` and `software_repair.php` both hold a `steps` array (check, download, backup, replace) run by `run(index)`; each `$.ajax` has an `error` callback and every refusal or failure goes to `fail(message)`, which turns the bar red, writes the message and enables the button again.
- Before #2987 the updater nested four requests and only the backup step had an `error` callback: a 500, a proxy 504 on the download or a dropped connection left the link spinning on "Updating..." with no message, and the panel has no global `ajaxError` handler.
- The Update control is a `<button type="button">`, `disabled` while the steps run (and when a folder refuses writes); labels never change ("Updating...", "Retry Update", "Repairing...", the permissions button's "Fixing" were removed, `button-busy-state.md`).

## Why do the completion addresses carry the form token?
- After the last step the screens go to `software_update.php?mode=autoupgrade` / `software_repair.php?mode=done` with `&token=` + `encodeURIComponent(software_token)`; both GET branches call `validate_token_field()` first, before the Site Log line, `software_update_available = 0`, the repair session unset and the cache deletes. Before, a forged link hid a pending update notice and planted a false Site Log line.
- An update from an older release whose JS sends no token still works: `pg_require_current_schema()` sends it to the installer first.

## Where does the step text go?
- Below the bar, in `<div class="logbox form-text" role="status">`. Inside `.progress-bar` Bootstrap 6 clips it (1rem high, `overflow: hidden`, `white-space: nowrap`) to the bar's current width and one line. `aria-valuenow` follows each width (`set_width()`; `software_backup_updateProgress()` in `backend.src.js` for the backup screen).

## Why does Return in Backup Name not download a backup?
- The field sits in `form#backupform`, whose first submit button is the first row's Download. A `keyup` handler runs after the browser's implicit submission, so Return downloaded a backup and started a new one. `backups.php` catches Return on `keydown` (skipping `event.isComposing`), prevents it and clicks `#backup`; the duplicate `keyup` handler in `backend.src.js` is gone.

## What happens when a backup cannot copy every file?
- `move_files` and `move_layouts` (`includes/panel/software.php`) copy through `pg_backup_copy_folder()` (`includes/fn/backup.php`): regular files only, and a file counts as failed when `copy()` fails or the copy's size differs. On any failure the step answers status `error` with `pg_backup_copy_failure_message()` (count, first three names, "Free some disk space and try again."), logs `Software Backup (…) failed: …` and removes the folder with `pg_backup_delete_tree()`, which also gives the space back.
- Before, `copy()` was never read: on a nearly full disk (the backup doubles data/files) a half-copied folder logged Success and was listed with a Download button by `backups.php`. The folder auto backup (`pg_backup_run_folder()`, no ZipArchive) uses the same helper and returns false with `pg_backup_last_error()`. A killed or timed-out request still leaves its folder.

## Other details
- Row buttons are named `Download {var:1}` / `Delete {var:1}` by the backup; the unfinished-restore time and the rollback archive times use `get_absolute_time(..., 'format' => 'plain_text')` (the person's zone, `panel-times-user-time-zone.md`).
- The heading, version arrow and Update ligatures are `aria-hidden` (`icon-ligature-button-names.md`).

Tests: `tests/update_screen_names_test.php`, `tests/backup_copy_folder_test.php`, `tests/software_update_redirect_token_test.php`.
