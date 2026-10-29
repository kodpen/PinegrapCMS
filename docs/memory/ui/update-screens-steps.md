---
name: update-screens-steps
description: Software Updater, Repair Software and Backup Manager: the step loop with an error path, the status line below the bar, Return in Backup Name, labels kept while they work (PR #2987)
---

## How do the update and repair screens run their steps?
- `software_update.php` and `software_repair.php` both hold a `steps` array (check, download, backup, replace) run by `run(index)`; each `$.ajax` has an `error` callback and every refusal or failure goes to `fail(message)`, which turns the bar red, writes the message and enables the button again.
- Before #2987 the updater nested four requests and only the backup step had an `error` callback: a 500, a proxy 504 on the download or a dropped connection left the link spinning on "Updating..." with no message, and the panel has no global `ajaxError` handler.
- The Update control is a `<button type="button">`, `disabled` while the steps run (and when a folder refuses writes); labels never change ("Updating...", "Retry Update", "Repairing...", the permissions button's "Fixing" were removed, `button-busy-state.md`).

## Where does the step text go?
- Below the bar, in `<div class="logbox form-text" role="status">`. Inside `.progress-bar` Bootstrap 6 clips it (1rem high, `overflow: hidden`, `white-space: nowrap`) to the bar's current width and one line. `aria-valuenow` follows each width (`set_width()`; `software_backup_updateProgress()` in `backend.src.js` for the backup screen).

## Why does Return in Backup Name not download a backup?
- The field sits in `form#backupform`, whose first submit button is the first row's Download. A `keyup` handler runs after the browser's implicit submission, so Return downloaded a backup and started a new one. `backups.php` catches Return on `keydown` (skipping `event.isComposing`), prevents it and clicks `#backup`; the duplicate `keyup` handler in `backend.src.js` is gone.

## Other details
- Row buttons are named `Download {var:1}` / `Delete {var:1}` by the backup; the unfinished-restore time and the rollback archive times use `get_absolute_time(..., 'format' => 'plain_text')` (the person's zone, `panel-times-user-time-zone.md`).
- The heading, version arrow and Update ligatures are `aria-hidden` (`icon-ligature-button-names.md`).

Test: `tests/update_screen_names_test.php`.
