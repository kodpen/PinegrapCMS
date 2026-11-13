---
name: picker-canvas-workspace
description: The Design Canvas's From the site and the Workspace's From the file manager on the picker (PK9b): canvas kinds, channel_file, the lists kept on a site page
module: picker, image_editor
---

## What opens From the site in the Design Canvas?
- `Editor.prototype.sitePicker(kind, title, ret)` (`pg_image_editor_artboard.js`) opens `pgPicker.open()` where the screen has `pgPicker`, `pgUI` and `#pg_picker_config`; the pick goes through `M.pickedFile()` (model, `SOUND` section) into the shape the editor's own lists handed on: `{id, name, url, width, height, size, svg}`, the name encoded as `encode_url_path()`.
- Import › From the site: kind `canvas_import` (jpg, jpeg, png, gif, webp, svg); an erased area's picture (`openImportFiles(pick)`): `canvas_picture` (no svg); Import › Video from the site and a clip's Replace: `canvas_video` (mp4, m4v, webm); the Animate tool's Add sound: `canvas_sound` (`pg_image_editor_sound_types()`). Types: `pg_picker_canvas_types()`, tested equal to the editor's own lists. Each stores the file id; the picker's Upload takes a file from this computer.
- The Sticker tool's Files tab is a panel list, not a chooser, and stays.

## Where does the editor keep its own lists?
- Where the picker is not on the screen: a site page in Edit mode (`get_page_content.php` calls `pg_image_editor_includes(false)`; no panel dialogs there, `picker-editor-bridge.md`). There the boxes of actions `sticker_files`, `video_files`, `sound_files` open as before, Add sound with From this computer at its top.
- Every panel screen gets the picker with the loader: `pg_image_editor_includes()` prints `pg_picker_script()` first (once per request).

## What opens From the file manager in the Workspace?
- `libraryPicker()` (`workspace.js`) opens kind `channel_file` titled "From the file manager"; the pick (`{id, name}`) goes to `ws_library_attach` as before. `includes/workspace/screen.php` prints `pg_picker_script()`.
- `channel_file`: every file type, no Upload (the paperclip has From this device), and `shut: [workspace, archived]`: `pg_picker_closed_folder_ids()` leaves out the Workspace folder (`config.ws_folder_id`) and archived folders with their sub-folders beside the Recycle Bin (pure `pg_picker_shut_folder_ids()`), as `ws_library_open()` did.
- The send keeps every check: `ws_library_file()` still refuses a team chat attachment as "no such file" (`platform/file-copy-chat-attachments.md`), the signing key and a file a channel refuses with their reasons. The picker does not grey those out ahead, as the drawer did.
- The old drawer stays only for a screen without the picker; PK10 removes it with `ws_library_browse` if nothing calls them.

## What changed for every file kind?
- `pg_picker_files_where()` adds `(folder > 0)`: a file without a folder (an ERP document, `erp/erp-document-archive.md`) is never listed in All or a search, as the editor's and the Workspace's own lists left them out.

## What could not be checked?
- Nothing was opened in a browser: the picker's `<dialog>` over the editor's `<dialog>` (both modal, the later one on top) and the focus given back were not seen.

Tests: `tests/image_editor_import_test.php` (`test_image_editor_import_picked_file`, `test_image_editor_import_site_picker_wired`), `tests/picker_test.php` (`test_picker_shut_folder_ids`, `test_picker_workspace_library_wired`). Related: picker-remaining-screens.md, picker-registry.md
