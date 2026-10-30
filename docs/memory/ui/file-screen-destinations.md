---
name: file-screen-destinations
description: Where Edit File, Upload File and Upload Design Files go after Save & Return, Delete, rotate, convert, duplicate, the back arrow and a finished upload
---

## Where do Edit File's Save & Return and Delete go?
- To `send_to` (`pg_send_to_url('')` through `pg_safe_redirect_path()`), else `view_files.php`; the `view_files` notice is still added.
- All Files opens Edit File with `send_to` = its own address (the `?filter=` it shows); the dashboard's Largest Files card with `welcome.php`.
- Symptom fixed: both went to bare `view_files.php`, the default filter, never back to the dashboard.

## Which reloads of Edit File keep send_to?
- Save, rotate (refused and done), SVG convert (copy and overwrite) and Duplicate append `&send_to=` from `pg_send_to_url('')`, so the back arrow still knows where the file was opened from.
- The rotate form (`#edit_file_rotate`, a separate `disable_shortcut` form) carries its own hidden `send_to`; convert and Duplicate post the main form, which has one.

## Where do the arrow and the breadcrumb go?
- Edit File: the arrow is `send_to`, else `view_files.php`; the `Files` crumb is `pg_send_to_list_url()` of `view_files.php`, so it never points at the dashboard.
- Upload File / Upload Design Files: the arrow is `pg_send_to_url(<list>)`, the crumb `pg_send_to_list_url(<list>)` (it was the bare list, so Cancel dropped the filter).

## Where does a finished upload go?
- To a server-resolved `json_encode(pg_send_to_url(<list>), JSON_HEX_…)`, not `$('#send_to').val()`: an absolute or `javascript:` `send_to` was followed.
- Pinned by `tests/file_screen_destinations_test.php`.

## What does Edit File's Duplicate copy?
- The file on disk and its folder, description, design flag, `optimized`, `image_width`, `image_height` and `optimization_percent` (empty size or saving as NULL), like the File Manager's copy (`includes/explorer/handle_files.php`).
- Symptom fixed: a copy of an optimised picture had `optimized = 0`, so the dashboard File Manager card offered it, View Files showed a saving for it and `optimize_image.php` compressed it again; the resize hint stayed off until a file screen measured it. Pinned by `tests/file_style_duplicate_test.php`.
