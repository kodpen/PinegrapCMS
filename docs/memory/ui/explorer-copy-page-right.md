---
name: explorer-copy-page-right
description: File Manager folder copy and page paste skip pages a role-3 user without 'Create pages' may not copy, and stay JSON
---

## What happens when a user without 'Create pages' copies a folder that holds pages?
- `pg_explorer_copy_folder()` (`view_folder_and_files_f.php`) checks `pg_explorer_can_copy_pages($user)` (role below 3, or `create_pages` set) before the pages loop. Without it each page is skipped with `Access denied for {name}.`, like design files; the folder, its files and subfolders still copy, the answer is `partial` and Undo gets the copy.
- Before, the loop called `duplicate_page()`, whose refusal is `output_error()`: an HTML page and exit inside the JSON request. The user saw "request failed", no Undo, and an empty `Name[1]` folder stayed behind (Duplicate, Copy/Paste, Ctrl/Alt-drag).

## And a plain page paste?
- The `page` case of `pg_explorer_handle_paste()` (`includes/explorer/handle_files.php`) reads the page, then refuses it by name when the right is missing or `check_edit_access()` fails on the page's folder, before `duplicate_page()`. Ctrl+C/Ctrl+V and Ctrl/Alt-drag reach it without `itemsCopyable()`.

## Why not refuse the whole folder copy?
- A folder with no pages copies today for such a user; only pages are refused. The client cannot know whether a folder holds pages, so the check stays on the server. `duplicate_page_f.php` keeps its own `output_error()` for its other callers.
