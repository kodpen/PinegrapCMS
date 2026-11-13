---
name: picker-site-lists
description: Design Canvas From the site (Import, Video, Add sound) and the Workspace paperclip's "From the file manager" on the one picker (PK9b, #9256): kinds, site page fallback, refused mark
module: picker, image_editor
---

## What opens the picker in the Design Canvas?
- Import › From the site: kind `canvas_picture` (jpg, jpeg, png, gif, webp, svg); an SVG becomes its own layer, a picture a picture layer (`importPicked()`). The erased area's Picture: `canvas_photo` (no SVG).
- Import › Video from the site and a clip's Replace: `canvas_video` (`pg_image_editor_clip_types()`). Add sound: `canvas_sound` (`pg_image_editor_sound_types()`); the picker's Upload stands in for the box's From this computer.
- All by id (`value` id): a pick becomes `{id, name, url, width, height, size, svg}` (`Editor.pickedFile()`), what the own lists handed on.

## Where does the editor keep its own lists?
- Where `sitePickerOk()` is false: no `pgUI` of Bootstrap 6 (a site page in Edit mode, `get_page_content.php`) or no `picker` in `pgImageEditorConfig`. There the boxes over the editor and the actions `sticker_files`, `video_files`, `sound_files` stay. Why: the picker is a Bootstrap 6 dialog; the site's own way is decided apart (`site-editor-picker-frame.md`).
- The Sticker panel's "Picture from Files" strip stays as it is: it is part of the panel, not a dialog.

## How is the picker loaded inside the editor?
- `pg_image_editor_config()['picker']` carries `pg_picker_config()` and the bundle's addresses. `pickerLoad()` uses `window.pgPicker` when the screen printed it, else writes `#pg_picker_config` and loads the script once.
- The picker opens with `showModal()` after the editor's own `<dialog>`, so it sits over it in the top layer; the editor's keys stay on its dialog. Focus goes back to the button that opened it, or to Import when that button closed with its menu.

## What does the Workspace paperclip open?
- "From the file manager" opens kind `channel_file` (`attachFromLibrary()` in `workspace.js`; `screen.php` prints `pg_picker_script()` in the channels mode). The drawer `libraryPicker()`, the action `ws_library_browse` and `ws_library_file_payload()` are gone.
- The kind: any type, no Upload (From this device uploads), files with a folder only. Closed with everything below them (`pg_picker_closed_folder_ids()`): the Recycle Bin, the Workspace folder, archived folders (`ws_library_closed_tops()`, which `ws_library_open()` uses too). Open only to the Workspace's members while it is on (`pg_picker_kind_open()`, 403 in `picker.php`).
- A file a channel refuses is listed with mark `refused` and the reason as `mark_text` (`ws_library_refusal()`: the signing key, a refused type, past `ws_upload_max_bytes()`); it cannot be chosen and the dialog notes "Marked files cannot be chosen.".
- `ws_library_attach` checks the file again (`ws_library_file()`): the chat attachment refusal (`platform/file-copy-chat-attachments.md`) and the Workspace folder answer stay there.
- Unlike the drawer, the picker never lists form and comment uploads (`attachment = 1`), as every picker kind.

Tests: `tests/picker_site_lists_test.php`, `test_picker_channel_and_canvas_kinds` in `tests/picker_test.php`, `tests/workspace_library_test.php`. Related: picker-registry.md, picker-dialog.md, image-editor-import.md, workspace/workspace-file-manager-files.md
