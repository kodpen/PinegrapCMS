---
name: explorer-restore-notes
description: What a File Manager restore says about items that came back changed (root folder, new name, page without its style) and what the bin badge names as the origin
---

## Where does a restored folder, page or file go?
- `pg_explorer_restore_target()` (`includes/explorer/handle_recycle.php`): the folder it came from, else the root with reason `gone` (folder deleted or none recorded) or `binned` (the folder is the bin or anywhere below it). Pure over `pg_explorer_folder_map()`; the restore and the bin listing both use it, so the badge and the restore never disagree.
- The root fallback and the `[1]` renaming are intended; only the silence was the bug.

## What does the restore answer say?
- `pg_explorer_restore_message()`: the count (or the refusals in a partial answer), then one note per item that came back changed, built by `pg_explorer_restore_notes()`: renamed because its name was taken, put in the root because its folder is gone or in the bin. Plain text (the toast uses `textContent`), names raw, no `h()`.
- A manager who restores a page whose style is still in the bin gets the page alone and a note naming the style that stays there; a designer gets the style back with it (renamed style noted too).
- The renamers report what they wrote: `pg_recycle_release_name()` returns `array('from', 'to')` (`array()` when nothing was parked), `pg_recycle_restore_style()` returns `name` and `original_name`; a short link compares its stored name with the `pg_short_link_free_name()` result.
- Symptom before: the toast said only "N item(s) were restored from the recycle bin" while a page lost its folder's Members/Private access by landing in the root, a page or `/files/` address changed with a `[1]` name, or a page came back with no style.

## What does the bin badge name as the origin?
- `pg_explorer_bin_entry($row, $days, $map, $now, $bin_id, $root_id)` (`handle_list.php`): the origin folder's name; when the restore will go to the root, "Root (its folder is gone)" or "Root (its folder Docs is in the recycle bin)", the binned folder unparked with `pg_recycle_parked_original()`. Rows other than folders, pages and files (styles, short links) name none.
- Before, it showed the raw `folder_name`: the parked `pgbin_<id>_Name` for a folder binned later and nothing for a gone folder.

Tests: `tests/explorer_handle_recycle_test.php`, `tests/explorer_handle_list_test.php`.
