---
name: image-editor-send
description: The Artifact Canvas's main button as a split button (#10858): the chevron's menu from M.sendMenu(), send to a Workspace channel, Download, who is offered what from a channel and from the File Manager
module: image_editor
---

## What is beside the main button?
- A split button: a narrow chevron button flush on the main button's right opens the canvas's own menu, its choices from `M.sendMenu()` (model, SEND section). Shown only when the menu has one; Export settings and Save as template follow outside a channel.
- `options.send` { channels() -> Promise of [{id, name}], url(id), current, onSent } lets the canvas be posted in a channel through `workspace_file_save.php?channel=`; the list is `ws_library_channels` (the ones the person may write in, `ws_library_postable()`), the current channel left out (`M.sendChannels()`). After a send the editor stays open.
- From a channel (`newCanvas()`, and a message's canvas in `editImage()`): main "Send the canvas", menu "Send to another channel…", the two Save to Files choices below, and "Download" (the PNG `drawArtboard()` draws).
- From the File Manager (`openImageEditorFor()`, New image): main "Save"; the menu gains "Send to a channel as a canvas…" only with the Workspace on (`wsSend`) and only for a canvas, as the endpoint takes a canvas only.
- From a channel also "Save to Files as a picture" and "as a video" (only with motion), for someone who may edit a folder: below.

## How is a channel's canvas saved to Files?
- Architect (2026-11-20): "Save to Files" means the File Manager; the channel's own folder is what "Send the canvas" already writes.
- Offered (canvas menu "Save to Files as a picture", "as a video" only with motion) only to someone who may edit a folder: staff, or a user granted edit rights (`pg_image_editor_files_edit()`, config `filesEdit`). Others keep "Send to another channel…" and "Download".
- A small window lists the folders they may edit as an indented select (action `folders`, `pg_image_editor_folder_choices()`: no bin, archived or Workspace branch; 403 without rights); the last one is remembered in the browser (`M.filesRemember()`).
- Picture: the artboard PNG with its document (save mode `new`, `folder_id`), so it opens as a canvas from Files; video: action `animation` with that folder. The name is the canvas's, unique. Each save asks `check_edit_access()` and refuses the bin and folder 0 again.
- Then one line "Saved to Files > <folder>" with "Open in Files". The message and its file in the channel stay as they are: a copy.

## Where is it tested?
- `tests/image_editor_send_test.php` (the menu's rights under Node, the channels, the callers, the labels); `ws_library_postable()` in `tests/workspace_library_test.php`.

Related: docs/memory/ui/image-editor-callers.md, docs/memory/ui/image-editor-workspace.md
