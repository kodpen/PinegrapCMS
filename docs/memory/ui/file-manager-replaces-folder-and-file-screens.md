---
name: file-manager-replaces-folder-and-file-screens
description: The folder screens (add_folder, edit_folder, their form) and the classic files list (view_files) are retired; where each of their jobs is in the File Manager, what is not there, the links that moved
---

## What was retired, and how does an updated site lose the files?
- #4889 (owner, 2026-11-01; `workflow/decisions-made-files.md`): `add_folder.php`, `edit_folder.php`, `includes/forms/folder_form.php`, `view_files.php`. They are in `clean_up.php`, so the Cleanup tool deletes them on an updated site.
- `tests/folder_and_file_screens_retired_test.php` fails when a product file names one of them again (`clean_up.php` and the migrations excepted).

## Where is each folder job now?
- Create: New Folder (`explorer_create_folder`), named in place. Rename: in place, the root folder too (tree menu). Move: drag, or cut and paste; the root cannot move.
- Order, archive and page styles: the Folder Settings drawer (`explorer_folder_settings_get` / `_set`, `includes/explorer/handle_folders.php`). For the root folder the drawer has the two styles alone, the site's default page styles, for managers and above; its order and archive stay as stored whatever is sent (`pg_explorer_folder_settings_order_archive()`).
- Access type and the users' rights: the Access Permissions drawer (`handle_folder_access.php`).
- Duplicate copies the whole tree (`pg_explorer_copy_folder()`). Duplicate with Find & Replace opens `duplicate_folder.php` (the folder and the pages directly in it, words replaced in the copies), which goes back to the File Manager.
- Delete: the recycle bin or its hard delete. The delete check counts the form upload and signature fields saving into the folder or a folder inside it (`upload_fields`), the warning the old Delete gave (`upload-folder-kept.md`).
- A folder's listing menu has no Edit entry; an `edit` action on a folder opens its settings drawer.

## Where are the classic files list's jobs?
- The Files view (`view_folders.php?view=files`): its scope menu (`?scope=`) has documents, media, attachments, archived and the five access types; Pictures is a view of its own. Sorting, the folder and modified-by columns, the optimized mark, the file window, download, image editor, optimize and resize (one or many), WebP, bulk edit (folder, design flag, description), delete through the bin, upload, create file and disk usage are there.
- Not there: the Optimize button's estimated saving percentage, a Description column (the preview panel shows it and the search reads it), sorting by several columns at once.

## Which links and notices moved?
- Dashboard widget 7 opens a recent folder as `view_folders.php?folder=N`. The files figure, widget 18, the panel search's Files action, `add_file.php`, `create_file.php`, `edit_file.php`, `optimize.php` and the cloud picker land on the Files view; their notices go to `liveform('view_folders')`, which the File Manager prints.
- The panel search has no Add Folder action; `get_help_url.php` has no rows for the three screens.
- `create_file.php` has no link left (the old list's toolbar was its only one); `edit_files.php` is posted only by `view_design_files.php`.

Related: docs/memory/ui/explorer-menu-tree.md, docs/memory/ui/main-menu-categories.md, docs/memory/ui/file-screen-destinations.md
