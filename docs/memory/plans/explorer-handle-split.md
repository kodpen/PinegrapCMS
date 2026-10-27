---
name: explorer-handle-split
description: Cutting pg_explorer_handle() (view_folder_and_files_f.php, ~7k lines) into sub-action handlers under includes/explorer/handle_*.php, ~500 lines a PR: the pattern, what is done, the cutting order
---

## What is the pattern?
- Each `case 'explorer_…':` body becomes `pg_explorer_handle_<action>($type, $request, $user)` in `includes/explorer/handle_<group>.php` (header block, `PG_FUNCTIONS_DIR` gate), required in place in `view_folder_and_files_f.php`. The `case` stays in the switch, calls the handler, then `break;`, so a handler that returns without `respond()` still ends in "Invalid request." as before.
- The body moves as it was (dedented); the comment above the `case` goes above the function, the `// ── title` line stays in the switch. A pure part gets its own function and a test.
- Source-reading tests that looked for the moved code are pointed at the new file.

## What is done?
Each slice has its tests in `tests/explorer_handle_<file>_test.php`.
- 1, short links (556 lines, #2109): `handle_short_links.php`; `short_link_requests_test.php` too.
- 2, backups (625, #2118): `handle_backups.php` (rename, copy, delete share one handler, as they shared a case).
- 3, zip, Office preview, rotate (451, #2132): `handle_zip.php`, `handle_files_media.php`.
- 4, upload, optimize, webp (986, #2145): `handle_upload.php`, `handle_files_media.php`.
- 5, catalog list, tree, group, rename, membership, pages (941, #2154): `handle_catalog.php`.
- 6, catalog paste, recycle, restore, purge, enable (1,196, #2164): `handle_catalog.php` (1,105 lines; `catalog_bin_test.php` reads it). A later catalog slice goes in a new `handle_catalog_*.php`.
- 7, bulk product options and edit (1,032, #2172): `handle_products_bulk.php`; `product_stock_events_test.php` reads it.
- 8, catalog quick edit, commerce access, file usage (1,189, #2192): `handle_catalog_edit.php`, `handle_file_usage.php` (takes `$folders_that_user_has_access_to`).
- 9, tree, new folder, new file, rename, move (459, #2223): `handle_items.php`; `file_withheld_test.php` reads it.
- 10, paste, delete files, design flag, folder options, folder settings (430, #2265): `handle_files.php`, `handle_folders.php` (options take `$folders_that_user_has_access_to`); test `explorer_handle_files_test.php`; `file_withheld_test.php` reads `handle_files.php`.
- 11, file window get/save, ERP tree/list, shared list (~470, #2269): `handle_file_window.php`, `handle_erp.php` (one handler for the shared case), `handle_shared.php` (takes `$folders_that_user_has_access_to`); test `explorer_handle_file_window_test.php`; `file_withheld_test.php` reads `handle_file_window.php`.

## In which order are the rest cut? (lines in the switch)
12. bulk page options/apply (184), bulk file options/apply (135), folder_access_get/set (213).
13. delete_check (135), recycle_delete (203), recycle_restore (211), hard_delete (58).
14. explorer_list (438) last: the most shared state.

## Where to start?
- The next slice in the order above that no open PR holds. Read the slice-1 diff first; check each body for `break` inside the case, `dirname(__FILE__)` paths and locals set before the switch.
- When slice 14 lands, `pg_explorer_handle()` is the prelude and the dispatch; move the lasting rules to `core/` and delete this plan and item 7.
