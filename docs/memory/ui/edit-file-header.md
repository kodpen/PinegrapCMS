---
name: edit-file-header
description: Edit File (edit_file.php) layout and status column: preview, Summary mirroring the File Manager details ("W × H px", optimized, last change with name, address), access, Where is it used?
module: files
---

## How is Edit File laid out?
- The design file screens' row (`pg_design_file_form_layout()`, `includes/forms/design_file_form.php`; `ui/edit-screen-wide-layout.md`), since #6740. Form column: Main Informations (file name), File Content (the code editor, for a viewable type), Description, Convert (SVG to raster, or a .webp copy). Sticky status column: the preview card (image, PDF, video, audio, icon) or the missing-file note, Summary, File Access Control (folder, Design File for a designer), Where is it used?. Sticky save bar: Save & Return, Save, Duplicate, Delete.
- Before, the editor's presence switched the cards between half and full width (`md:col-6`/`md:col-12`), the preview took a full-width row and the facts were loose lines under the heading.

## What does the Summary show?
- File size and, for an image, `pg_image_dimensions_label()` ("600 × 600 px", the File Manager's form; it was "600px - 600px").
- For an image with the image buttons, Optimized (Yes / "Not optimised"); Access (coloured by the folder's access control); the last change as exact time, then relative time "by" the editor; the full address as selectable text with a copy button (only when the file is on disk).
- The breadcrumb is File Manager › the file's folder (`view_folders.php?folder=<id>`) › file name. The rotate buttons are "Rotate Left" / "Rotate Right" (one key spelling).

## Whose name is written beside a change?
- `pg_user_display_name($first, $last, $username)` (`includes/fn/files.php`): the first and last name of the user's contact (`user.user_contact` → `contacts`), else the username, as the Workspace's `ws_display_name()` does. Edit File joins `user` and `contacts` for it.

## How does "Where is it used?" work there?
- Its card's button posts the File Manager's own `explorer_file_usage` action to `api.php` (`file_explorer`, `software_token`) only when pressed, and draws the groups below it like the File Manager's panel. No second search exists.

Fixed #5222 (the header lacked these). Pinned by `tests/edit_file_header_test.php`.
