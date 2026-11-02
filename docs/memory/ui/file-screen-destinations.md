---
name: file-screen-destinations
description: Where Edit File, Upload File and Upload Design Files go after Save & Return, Delete, rotate, convert, duplicate, the back arrow and a finished upload
---

## Where do Edit File's Save & Return and Delete go?
- To `send_to` (`pg_send_to_url('')` through `pg_safe_redirect_path()`), else the File Manager's Files view (`view_folders.php?view=files`); the notice goes to `liveform('view_folders')`, which the File Manager prints (#4889).
- The dashboard's Largest Files card opens Edit File with `send_to` = `welcome.php`.
- Symptom fixed: both went to the bare files list, never back to the dashboard.
- The row Edit File and Image Editor buttons of Design Files, and the Image Editor link of Edit File and Edit Design File, `urlencode()` `send_to` and `file_name`. A view change gives `?filter=default&filter=my_photos`; unencoded, PHP read `send_to` up to the first `&` and Back landed on the old view, and a legacy name with `&`, `+` or `#` opened the wrong file. Pinned by `tests/file_list_links_encoding_test.php`.

## Which reloads of Edit File keep send_to?
- Save, rotate (refused and done), SVG convert (copy and overwrite) and Duplicate append `&send_to=` from `pg_send_to_url('')`, so the back arrow still knows where the file was opened from.
- The rotate form (`#edit_file_rotate`, a separate `disable_shortcut` form) carries its own hidden `send_to`; convert and Duplicate post the main form, which has one.

## Where do the arrow and the breadcrumb go?
- Edit File: the arrow is `send_to`, else the Files view; the `File Manager` crumb is `pg_send_to_list_url()` of `view_folders.php`, so it never points at the dashboard.
- Upload File / Upload Design Files: the arrow is `pg_send_to_url(<list>)`, the crumb `pg_send_to_list_url(<list>)` (it was the bare list, so Cancel dropped the filter).

## Where does a finished upload go?
- To a server-resolved `json_encode(pg_send_to_url(<list>), JSON_HEX_…)`, not `$('#send_to').val()`: an absolute or `javascript:` `send_to` was followed.
- Pinned by `tests/file_screen_destinations_test.php`.

## What does Edit File's Duplicate copy?
- The file on disk and its folder, description, design flag, `optimized`, `image_width`, `image_height` and `optimization_percent` (empty size or saving as NULL), like the File Manager's copy (`includes/explorer/handle_files.php`).
- Symptom fixed: a copy of an optimised picture had `optimized = 0`, so the dashboard File Manager card offered it, the files list showed a saving for it and `optimize_image.php` compressed it again; the resize hint stayed off until a file screen measured it. Pinned by `tests/file_style_duplicate_test.php`.

## Is a blank name refused?
- Edit File (after the Delete branch, so Delete works with an empty box), Edit Design File (save branch only) and the File Manager's file window (before the blocked-name check) refuse a name that is empty or only spaces with "Please enter a name.", like the File Manager's rename (`handle_items.php`).
- `prepare_file_name()` keeps turning a blank name into `file`; uploads and imports need that default.
- Symptom fixed: a rename to spaces renamed the stored file, on disk and in its record, to `file` with no extension; links to it returned 404 and a theme CSS file lost its stylesheet. Pinned by `tests/file_rename_blank_test.php`.
