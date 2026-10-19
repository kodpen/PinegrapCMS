---
name: erp-documents-in-file-manager
description: Why a file the ERP module keeps (erp_doc_type set, no folder) is read-only in the file manager, the four doors that refuse it by name, and the image editor's save that used to write over it
---

## What is an ERP document row?
- An issued invoice's PDF, a delivery note, an e-document, a receipt's picture or an expense's scan: a `files` row with `erp_doc_type <> ''` (column from 4.65, `includes/erp/archive.php`), named `erp-…`, with `folder = 0`. `get_file.php` serves it only to a signed-in user with the ERP right; to anyone else it does not exist.
- `pg_files_include_erp_document($file_ids)` (`includes/fn/files.php`) says whether any of the ids is one; it answers false on a database without the column.

## Why must every write door ask for it by name?
- A file with no folder passes `check_edit_access(0)` for staff, and a file with no folder and no `erp-` name is public. So a screen that renames, replaces, deletes or copies a file must refuse ERP rows itself, or an issued invoice could be renamed, overwritten, or copied beside itself as a public file.
- The doors that ask: `edit_file.php` (the file's screen), `pg_explorer_handle()` for every type in `pg_explorer_file_id_actions()` (`view_folder_and_files_f.php`), the explorer's file read in `includes/panel/explorer.php`, and `image_editor_save.php` (added in PR #517: in replace mode it wrote over the picture the row named, in copy mode it placed the edited copy in folder 0; both refused now with the shared sentence "ERP documents are read-only in the file manager."). Test: `tests/files_erp_readonly_test.php`.
- The Workspace does not reach these rows: `ws_file_edit_target()` works on the channel folder's copies only.

## What does a new file-writing endpoint do?
- Read the row first, then `pg_files_include_erp_document(array($row['id']))` before the folder right, and refuse with the same sentence (JSON endpoints answer in JSON, status 403). A new `explorer_*` type that takes a file id goes into `pg_explorer_file_id_actions()` and the allowlist (docs/memory/core/explorer-subaction-registration.md).
