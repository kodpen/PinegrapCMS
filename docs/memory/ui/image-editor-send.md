---
name: image-editor-send
description: The Artifact Canvas's main button as a split button (#10858): the chevron's menu from M.sendMenu(), send to a Workspace channel, Download, who is offered what from a channel and from the File Manager
module: image_editor
---

## What is beside the main button?
- A split button: a narrow chevron button flush on the main button's right opens the canvas's own menu, its choices from `M.sendMenu()` (model, SEND section). Shown only when the menu has one; Export settings and Save as template follow outside a channel.
- `options.send` { channels() -> Promise of [{id, name}], url(id), current, onSent } lets the canvas be posted in a channel through `workspace_file_save.php?channel=`; the list is `ws_library_channels` (the ones the person may write in, `ws_library_postable()`), the current channel left out (`M.sendChannels()`). After a send the editor stays open.
- From a channel (`newCanvas()`, and a message's canvas in `editImage()`): main "Send the canvas", menu "Send to another channel…" and "Download" (the PNG `drawArtboard()` draws).
- From the File Manager (`openImageEditorFor()`, New image): main "Save"; the menu gains "Send to a channel as a canvas…" only with the Workspace on (`wsSend`) and only for a canvas, as the endpoint takes a canvas only.
- "Save to Files as a picture" and "as a video" (only with motion) are in `M.sendMenu()` behind `c.files`, a folder the caller gives; no caller gives one yet (where they land from a channel is asked of the architect in #10858).

## Where is it tested?
- `tests/image_editor_send_test.php` (the menu's rights under Node, the channels, the callers, the labels); `ws_library_postable()` in `tests/workspace_library_test.php`.

Related: docs/memory/ui/image-editor-callers.md, docs/memory/ui/image-editor-workspace.md
