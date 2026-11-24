---
name: bug-hunt-areas-file-manager-permissions
description: Bug hunt 53, File Manager permissions (2026-11-24, PR #11492): who sees, moves, renames and deletes a file or folder, the Recycle Bin, design files, path checks; three faults fixed
---

## What was the area?
- Hunt 53, "file-manager-permissions": who may see, move, rename and delete a file or folder in the File Manager, the Recycle Bin, a design file against the designer rule (role 1 or 0), path checks. PR #11492.
- Files read: `includes/explorer/handle_items.php` (tree, create, rename, move), `handle_recycle.php`, `recycle_bin.php`, `handle_files.php` (paste, delete, design flag), `handle_file_window.php`, `handle_folder_access.php`, `handle_upload.php`, `handle_zip.php`, `handle_list.php`, `pg_explorer_copy_folder()` and the dispatcher in `view_folder_and_files_f.php`, `edit_design_file.php` with `includes/forms/design_file_form.php`, the `designer_file` actions of `includes/panel/designer.php`.

## What did it fix?
- A manager sent a folder holding design files to the Recycle Bin, though a design file alone is refused: the theme's files went private, the manager could not restore them, and the purge deleted them. Now refused (`ui/explorer-folder-bin-rights.md`).
- Edit Design File and the design files list's quick edit renamed `dkim.key`, which is a design file; under the new name `get_file.php` served the mail signing key. Now "Access denied" (`platform/dkim-key-in-file-manager.md`).
- The visual designer's `designer_file` read, save and delete reached an ERP document by its stored name: an issued invoice read without the ERP right, overwritten or deleted. Now refused (`platform/erp-documents-in-file-manager.md`).

## What was read and found clean?
- Rename, move, paste, zip, file window save, optimize and WebP ask the folder's edit right and the designer rule for design files on every row; the target folder's edit right and the bin on every move or paste; a folder never moves into its own subtree; the root never moves.
- Recycle Bin: a binned row is not renamed, copied or saved; a restore asks the target's edit right and the designer rule; a drag out of the bin restores the name; hard delete and the purge decide for the given user (`core/recycle-bin-purge-rights.md`).
- Listing, tree and the flat views list only folders `check_folder_access_in_array()` grants; a basic user starts at the top of each granted subtree. Per-user folder rights are written by roles 0-2 for basic users only.
- Uploads and archive extraction refuse blocked names and run `prepare_file_name()`; extraction checks the stored name again (`dkim.key`, scripts).

## What was seen and left?
- Staff see every folder of the Workspace in the File Manager, private channels' too, and open a text file's content in the file window, while `get_file.php` refuses staff a private channel's file by its address. Written behaviour (`workspace/workspace-file-manager-files.md`: "staff every folder"), so not a fault of this hunt; whether the file window should follow the address gate is a question for the architect.
- `explorer_hard_delete` deletes a live item for good when named by id although the bin is installed; the screen only offers it inside the bin, and the rights are the same.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/ui/explorer-bin-keys.md
