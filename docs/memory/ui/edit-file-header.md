---
name: edit-file-header
description: Edit File header (edit_file.php) mirrors the File Manager details: "W × H px", folder crumb, optimized state, last change with name, address, Where is it used?
module: files
---

## What does the header of Edit File show?
- File size and, for an image, `pg_image_dimensions_label()` ("600 × 600 px", the File Manager's form; it was "600px - 600px").
- Access, then for an image the optimized state ("Optimized" / "Not optimised"), the last change as exact time · relative time "by" the editor, and the full address as selectable text with a copy button (only when the file is on disk).
- The breadcrumb is File Manager › the file's folder (`view_folders.php?folder=<id>`) › file name.
- The rotate buttons are "Rotate Left" / "Rotate Right" (one key spelling).

## Whose name is written beside a change?
- `pg_user_display_name($first, $last, $username)` (`includes/fn/files.php`): the first and last name of the user's contact (`user.user_contact` → `contacts`), else the username, as the Workspace's `ws_display_name()` does. Edit File joins `user` and `contacts` for it.

## How does "Where is it used?" work there?
- A button under the toolbar posts the File Manager's own `explorer_file_usage` action to `api.php` (`file_explorer`, `software_token`) only when pressed, and draws the groups below it like the File Manager's panel. No second search exists.

Fixed #5222 (the header lacked these; the delete button sat where the usage could not be seen).
