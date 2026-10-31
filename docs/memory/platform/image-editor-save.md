---
name: image-editor-save
description: The image editor's save: image_editor_api.php open and save, the document check, the engine's edit option (turn, flip, straighten, crop, size, overlay, shape), replace, copy and new
module: image_editor
---

## Where does the editor open and save a file?
- `image_editor_api.php` (root; JSON in and out, refusals too) runs `pg_image_editor_api()` in `includes/fn/image_editor.php`: POST only; a signed-out request gets 401 `code: session` before `validate_user()`; the token is compared by hand (403, `code: session`, "Your session expired. Reload the page and try again.").
- `open {file_id}`: name, stamped URL, `replace` (false for an animated file, and for a design file below Designer), `notice`. `save {mode, doc, file_id | folder_id, overlay, picture, name}` answers the saved file: id, name, folder, url, width, height, size.

## What does a save check before any pixel work?
- `pg_image_edit_validate($doc)`: version 1; JSON numbers (a numeric string is refused) in the editor's own ranges: quarter turns, flips as booleans, straighten ±45°, a crop inside the turned space with ½ px slack, output 1-8000 px, at most 500 layers, an artboard 16-8000 px. Anything else is "Invalid request." with nothing drawn.
- The overlay (the layers drawn by the browser) and a new image's picture must be PNG data: URLs of exactly the output or artboard size (`pg_image_editor_take_png()`, decoded in pieces).

## How are the pixels made?
- From the original on the server, never from a picture the browser drew: `pg_image_edit_apply()` gives the recipe (rot, flip_x, flip_y, straighten, crop, space_w, space_h, shape, out_w, out_h, overlay, first_frame) to `pg_process_image_file()`'s `edit` option with `write => false` and quality 92.
- Both libraries (`pg_image_edit_imagick()`, `pg_image_edit_gd()`) follow the preview's `pictureMatrix()`: quarter turn, flips of the turned picture, straighten scaled by the cover scale (`pg_image_edit_cover_scale()` = the model's `coverScale()`), crop, output size, overlay, crop shape. The shape comes last because the preview clips the layers to it.
- The crop is measured in the browser's turned space (`space_w` x `space_h`); a file of another size is cropped at the same place (`pg_image_edit_plan()`).
- Shapes: Imagick draws a mask; GD clears whole pixels by rows and gives those the curve crosses a 4 x 4 coverage (a smooth edge). Without alpha (JPEG, BMP) the corners are white.
- `tests/image_editor_save_test.php` holds GD and Imagick to the preview: Node gives the model's source point of samples of the result, a gradient picture's pixels say where each came from.

## What do replace, copy and new write?
- replace: the file in place (temp file and rename), same id and name, so links keep working; the row's size and dimensions, `optimized = 0` (fresh bytes encoded for quality: Optimize is offered again), audit `update`.
- copy: `get_unique_name()` beside it, a new row (`design = 0`), audit `create`. An animated file gives its first frame (`first_frame`: Imagick reads `<path>[0]`, GD reads one frame anyway); the file itself keeps its animation.
- new: an artboard with `save.folderId`; the browser's PNG, recompressed by the engine when smaller, as `<name>.png`.

## What does the browser do?
- `save.mode` replace, copy, new or callback; the default comes from `fileId` (replace) or `blank` with `save.folderId` (new); without `pgImageEditorConfig.api` it is callback (the document to `onSave`, nothing written).
- A file opens through `open`; Save writes over it and its caret menu has "Save as a copy"; when the server says replace is not allowed, Save is the copy. An unchanged replace closes without writing. Ctrl/Cmd+S saves; Escape closes the menu on the key itself (the dialog's cancel event does not come for every press).
- While a save runs the stage takes no input; a refusal keeps the editor open with the server's sentence in a toast, and the stage takes the focus back so the keys work.
