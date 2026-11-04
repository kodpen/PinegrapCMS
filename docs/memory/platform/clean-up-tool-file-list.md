---
name: clean-up-tool-file-list
description: clean_up.php $file_list and data/temp sweep: never list a live file name, how to verify before adding, and $temp_keep for non-regenerable temp files.
---

## Why can't the `clean_up.php` list hold a live file name?
- `$file_list` in `clean_up.php` means "safe to delete" and the Cleanup tool applies it literally. `image_editor_save.php` sat there as a LiveSite leftover while the same name served the previous image editor's endpoint. An operator who ran Cleanup deleted a live endpoint, and every save in the image editor then returned 404. That editor was removed in #4609; its endpoint, modal script and library folder are listed now.
- The symptom links to no cause anywhere: the tool says "cleaned", the editor says "Görsel kaydedilemedi. (404)", `error.log` has no line (there is no error, the file is gone).

## What do I check before touching the list?
- Before adding a name verify nothing calls it: `grep -rn "<name>" --include=*.php --include=*.js .`
- Reusing an old name for a new file reaches the same door: before giving an old name to a new file, check whether it is in the list.

## What about `data/temp`?
- The same file sweeps `data/temp` as a DIRECTORY, so any new file placed there lands in the delete-without-asking list. It is the right place for regenerable cache. Anything not regenerated, or not regenerated as the SAME file, must be written to `$temp_keep` (example: `hash_reference.json`).
- Kept: `pg_clean_up_temp_keep()` (`includes/fn/files.php`, `tests/clean_up_list_test.php`) with the reason for each: `hash_reference.json`, `erp_packages/` (the accountant's link reads its ZIP), `csp_reports.json`, `php_errors.log`, `shield_pending.log` (bans waiting for the database), `waf_ban_notice.log` (queued ban notices), `ai_write_usage.json` (the daily AI counter) (#2060), and `server_config/`, the web.config / .htaccess copies `pg_server_config_backup()` keeps before the server rules repair rewrites them (#5870). A cache whose loss only costs a rebuild (`system_status_cache.json`, `db_health_cache.json`, `ban_shield.txt`, which `waf_ban_shield_refresh()` rewrites) stays sweepable.

## How does the delete report what happened?
- The button asks first (`data-confirm-content` with the listed count) and is `theme-danger` (#5871).
- Each path goes through `pg_clean_up_delete_path()`, true only when it is gone; a path still there (no right, held open) is not counted or logged as deleted and is named in a warning (#5873). The counts sentence comes from `pg_clean_up_count_text()`, '' for 0/0, so an emptied list says "not found" instead of " that deleted.".
- It returns to `system_tools.php` (`liveform('system_tools')`), as Clear Cache does (#5872).

## How is the list of what Cleanup deletes printed?
- Each line comes from `pg_clean_up_list_row($icon, $name)` (`includes/fn/files.php`), which escapes the name. An orphan in `data/files` arrived outside the uploader (FTP, an unpacked archive) and keeps any name; before #5142 a name holding markup ran in the panel when the screen was opened.

## How is the screen laid out?
- The product_builder layout (`ui/edit-screen-wide-layout.md`, #6839): the list in an Items to Delete card, a sticky Summary (`pg_clean_up_summary()`: folders, files, and the expired `iyzipay_3ds_state` rows only with e-commerce on) and the sticky bar with the delete button.
