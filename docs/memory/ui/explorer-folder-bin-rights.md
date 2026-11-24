---
name: explorer-folder-bin-rights
description: Who may send a folder to the File Manager's Recycle Bin: the subtree's pages need a basic user's Delete pages right, its design files a designer or administrator (pg_explorer_folder_bin_refusal())
---

## What does a folder sent to the bin need?
- The folder takes its whole subtree along, and the daily purge deletes it later as the software (`core/recycle-bin-purge-rights.md`). So `explorer_recycle_delete` asks, for a folder, what a page or a design file binned on its own asks.
- A basic user (role 3) without Delete pages: refused when the subtree holds a page, "You do not have access to delete pages."
- A manager or a basic user (role > 1): refused when the subtree holds a design file, "Design files in this folder can only be deleted by a designer or administrator."
- `pg_explorer_folder_bin_refusal($user, $subtree_pages, $subtree_design_files)` (`includes/explorer/handle_recycle.php`) answers the message or ''; test `tests/explorer_handle_recycle_test.php`.

## Why was the design file rule added?
- A manager could not bin a design file, but could bin the folder holding it: the theme's CSS and scripts went private, a manager could not restore the file, and the purge deleted it 30 days later. Fixed in the file manager permissions bug hunt (`workflow/bug-hunt-areas-file-manager-permissions.md`).

## What does the delete window show?
- `explorer_delete_check` lists its blockers for a permanent delete only; in bin mode the window lets the name be typed and the server's refusal comes back as the toast.

Related: docs/memory/ui/explorer-bin-keys.md, docs/memory/core/recycle-bin-purge-rights.md
