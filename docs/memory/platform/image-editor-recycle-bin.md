---
name: image-editor-recycle-bin
description: The image editor's server refuses the Recycle Bin: a binned file is not opened, replaced or copied, and a new picture is not written into the bin folder (#4297)
module: image_editor
---

## What does the editor do with a file in the Recycle Bin?
- Nothing: `pg_image_editor_file()` answers "Sorry, we could not find the file." when the row's folder is the bin or below it (`pg_folder_binned()`), before the access check and before the file is read. open, save replace and save copy all go through it.
- save new (an artboard) refuses a `folder_id` in the bin with "Access denied" (403), as the File Manager's upload (`includes/explorer/handle_upload.php`) and move (`handle_file_window.php`) refuse it with `pg_recycle_is_inside()`.

## Why?
- Bug #4297: a binned picture (`pgbin_<id>_…`) opened through the API and saved as a copy left `pgbin_<id>_…[1].jpg` in the bin folder with no `recycle_bin` row: not in the bin's list, not in the File Manager tree, and its "move to bin" answered "access denied"; only a hard delete removed it. Replace wrote over the binned file.
- `pg_folder_binned()` (`includes/authentication.php`) is used, not `pg_recycle_is_inside()`: the explorer's file is not loaded by `image_editor_api.php`.

## Where is it tested?
- `tests/image_editor_recycle_bin_test.php` (the source order of the checks; the endpoint needs a database).
