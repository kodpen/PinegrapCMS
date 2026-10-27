---
name: explorer-handle-split
description: Cutting pg_explorer_handle() (view_folder_and_files_f.php, ~7k lines) into sub-action handlers under includes/explorer/handle_*.php, ~500 lines a PR: the pattern, what is done, the cutting order
---

## What is the pattern?
- Each `case 'explorer_…':` body becomes `pg_explorer_handle_<action>($type, $request, $user)` in `includes/explorer/handle_<group>.php` (header block, `PG_FUNCTIONS_DIR` gate), required in place in `view_folder_and_files_f.php`. The `case` stays in the switch, calls the handler, then `break;`, so a handler that returns without `respond()` still ends in "Invalid request." as before.
- The body moves as it was (dedented); the comment above the `case` goes above the function, the `// ── title` line stays in the switch. A pure part gets its own function and a test.
- Source-reading tests that looked for the moved code in `view_folder_and_files_f.php` are pointed at the new file.

## What is done?
- Slice 1, short links (556 lines, #2109): `handle_short_links.php`, seven handlers and `pg_explorer_short_link_destination_columns()` (tests `explorer_handle_short_links_test.php`, `short_link_requests_test.php`).
- Slice 2, backups (625 lines, #2118): `handle_backups.php`, six handlers (rename, copy and delete share one, as they shared one case), `pg_explorer_backup_breadcrumb()` and `pg_explorer_backup_entry_segments()` (test `explorer_handle_backups_test.php`).
- Slice 3, zip, Office preview and rotate (451 lines, #2132): `handle_zip.php` (zip_create, zip_extract, `pg_explorer_zip_archive_name()`, `pg_explorer_zip_entry_file_name()`) and `handle_files_media.php` (office_preview, rotate, `pg_explorer_rotatable()`) (test `explorer_handle_zip_test.php`).
- Slice 4, upload, optimize and webp (986 lines, #2145): `handle_upload.php` (upload, `pg_explorer_upload_image_options()`) and `handle_files_media.php` (optimize, webp, `pg_explorer_batch_status()`, `pg_explorer_webp_base_name()`) (test `explorer_handle_upload_test.php`).
- Slice 5, catalog list, tree, create group, rename, membership remove and pages (941 lines, #2154): `handle_catalog.php`, six handlers, `pg_explorer_catalog_bin_entries()` and `pg_explorer_catalog_short_description_follows_name()` (test `explorer_handle_catalog_test.php`).
- Slice 6, catalog paste, recycle, restore, purge and enable (1,196 lines, #2164): `handle_catalog.php` (1,105 lines now), five handlers, `pg_explorer_catalog_bin_item_type()` and `pg_explorer_catalog_recycle_message()` (test `explorer_handle_catalog_test.php`; `catalog_bin_test.php` reads the new file). A later catalog slice goes in a new `handle_catalog_*.php`.

## In which order are the rest cut? (lines in the switch)
7. bulk_product_options (63), products_bulk_edit (432).
8. catalog_quick_edit (174), catalog_access_get/set (110), file_usage (275).
9. tree (33), create_folder (55), create_file (70), rename (148), move (173).
10. paste (163), delete_files (61), folder_options (55), folder_settings_get/set (86), files_design (69).
11. file_get/file_save (194), erp_tree/erp_list (111), shared_list (125).
12. bulk page options/apply (184), bulk file options/apply (135), folder_access_get/set (213).
13. delete_check (135), recycle_delete (203), recycle_restore (211), hard_delete (58).
14. explorer_list (438) last: the most shared state.

## Where to start?
- The next slice in the order above that no open PR holds. Read the slice-1 diff first; check each body for `break` inside the case, `dirname(__FILE__)` paths and locals set before the switch.
- When slice 14 lands, `pg_explorer_handle()` is the prelude and the dispatch; move the lasting rules to `core/` and delete this plan and item 7.
