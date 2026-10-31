---
name: image-editor-keys
description: The image editor's keys and gestures: shortcuts, Escape on keydown and the reopened dialog, Enter and Space left to focused buttons (ownsKey), panning, double click
module: image_editor
---

## Which shortcuts and gestures work?
- Ctrl/Cmd+Z undo, Ctrl/Cmd+Shift+Z or Ctrl+Y redo; + and - zoom, 0 fits, 1 is actual size. Ctrl/Cmd and the wheel (a trackpad pinch) zoom at the pointer; the wheel pans, with Shift sideways. Space and drag, the middle button, or two fingers move the view; two fingers pinch. One finger and the left button stay free for the tools.
- The full-screen button sets a full-window class and asks the browser for full screen; leaving the browser's full screen drops back to the window. Double click (two taps on touch) on the stage: actual size there from the fitted view, else fit again.

## What does Escape do, and why on keydown?
- Escape closes the unsaved-changes question or the save menu, else cancels the crop, else clears the layer selection, else leaves full screen, else closes (asking first when the document changed). The layer keys (arrows, Delete, Ctrl+D, Ctrl+C, Ctrl+A): `image-editor-layers.md`.
- It is handled on `keydown` with `preventDefault()` (PR #4324). Chromium cannot hold a second dialog `cancel` without a click between, so a `cancel`-only handler let the second Escape close the window unasked and the editor hung until a reload (#4212). The `cancel` listener stays for other close requests (a back gesture).
- A `close` the editor did not make (`finish()` sets `finished`) reopens the dialog with its state, the question too; where that fails it ends as unsaved, so the promise settles and `open()` works again.

## When do Enter and Space reach a button?
- On a focused button, link, chip or text field they press it (`PgImageEditorModel.ownsKey()`, tested in `tests/image_editor_model_test.php`); a checkbox keeps Space only. On the stage, a slider or the dialog Enter applies the crop and Space pans (#4213, #4294).
- Crop's Cancel brings back the unsaved flag it had on entry, so Escape after a cancelled crop closes without asking when nothing else changed.
