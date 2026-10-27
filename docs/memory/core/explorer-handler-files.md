---
name: explorer-handler-files
description: pg_explorer_handle() after the split: the prelude and the dispatch, one handler per sub-action in includes/explorer/handle_*.php, where a new one goes, the tests
---

## What is left in `pg_explorer_handle()`?
- Since the split (#2109 to #2351): the prelude (short links refused to role 3, ERP documents read-only for `pg_explorer_file_id_actions()`) and the switch. Each `case 'explorer_…':` calls `pg_explorer_handle_<action>($type, $request, $user)` and then `break;`, so a handler that returns without `respond()` still ends in "Invalid request.". A `// ── title` line stays above each case; the longer comment sits above the handler.
- Handlers that filter by folder rights also take `$folders_that_user_has_access_to` (listing, shared, file usage, folder options).

## Where does a new sub-action go?
- Its handler goes in the `includes/explorer/handle_<group>.php` of its group (Pinegrap header, `PG_FUNCTIONS_DIR` gate, required in `view_folder_and_files_f.php` beside the others), never inline in the switch. A new group gets a new file; `handle_catalog.php` is full (over 1,000 lines), so a catalog one goes in a new `handle_catalog_*.php`.
- Paths are built from `PG_FUNCTIONS_DIR` (`seo.php` is `PG_FUNCTIONS_DIR . '/seo.php'`); `dirname(__FILE__)` in a moved body pointed at the product root and is wrong under `includes/explorer/`.
- It is also added to the `file_explorer` allowlist (`explorer-subaction-registration.md`).

## Which file holds which sub-actions?
- `handle_list.php` listing (folder, bin, flat views); `handle_items.php` tree, new folder/file, rename, move; `handle_files.php` paste, delete files, design flag; `handle_folders.php` folder options and settings; `handle_recycle.php` delete pre-flight, recycle, restore, hard delete; `handle_file_window.php`, `handle_files_media.php` (Office preview, rotate, optimize, webp), `handle_upload.php`, `handle_zip.php`, `handle_file_usage.php`, `handle_bulk_edit.php`, `handle_folder_access.php`, `handle_shared.php`, `handle_erp.php`, `handle_short_links.php`, `handle_backups.php`, and the catalog: `handle_catalog.php`, `handle_catalog_edit.php`, `handle_products_bulk.php`.

## How is a handler tested?
- `tests/explorer_handle_<file>_test.php`: the handler is defined in its file and not twice, the case calls it, the file has the gate and no `__FILE__`, and its pure helpers (status answers, filters, subtree order, bin entry) have their own cases. A source-reading test looks in the handler file, not in `view_folder_and_files_f.php`.
