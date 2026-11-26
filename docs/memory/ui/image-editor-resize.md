---
name: image-editor-resize
description: The Canvas tool's "Resize to other sizes" (AC9, #11975): the rule placing and scaling layers on another size, the panel, each size saved as a new canvas in Files, the texts under the floor
module: image_editor
---

## What does a person see?
- In the Canvas tool on a canvas (not a photo's canvas), under Canvas size, the button "Resize to other sizes". Opened: the five presets as tick rows ("Square · 1080 × 1080"), "Custom size" with width × height (typing a side ticks it), a note, then "Make {n} sizes" (or "Make 1 size").
- Make waits with a sentence when nothing is ticked, and where Files cannot be reached (a channel's or a caller's canvas): "Save this canvas in Files to make other sizes.".
- After Make a line under the button: "{n} sizes made in Files." (or "{done} of {n} sizes were made." when one failed), then "Text smaller than 12 px: ..." naming the texts, with Show switching to Select and selecting those on the open scene.

## Where do the sizes go?
- Each size is drawn from a copy (`M.resizeDoc()`) and saved one after the other: a canvas opened from Files as a copy beside its file (mode copy, `copy_name` "<name> <w>x<h>"), a new canvas made in a folder into that folder (mode new, `name`). Export settings never go with them. A failure stops the rest.
- The open canvas is not changed, and nothing opens: the caller's `options.onCreated(files)` hears of them (the File Manager reloads).
- `copy_name` is read only by a canvas copy (`pg_image_editor_save_canvas()`); without it a copy takes the canvas's name as before.

## What is the rule? (model section RESIZE)
- Units: each outermost group is one unit (`resizeUnits()`), every other layer one. k = the scale fitting the old board in the new one (the smaller of the two side ratios).
- A unit scales by k. Its place on each side (`resizeAxis()`): middle in the first third keeps its distance from that edge times k, in the last third from the far edge, between keeps its middle at the same share of the side.
- A unit spanning 90 % of a side (`RESIZE_SPAN`): a rect, ellipse or svg layer, alone and not turned (`RESIZE_STRETCH`), stretches that side with the board (a bar; both sides, a background). Anything else spanning both sides covers the new board by the larger ratio, centred; a picture or text never stretches.
- Text size, stroke, a rect's corners scale by the smaller axis scale; motion keys' x and y by each axis's scale. Every scene's layers are resized; the background stays.
- A text at 12 px or more that falls under 12 px (`RESIZE_FLOOR`) is listed `{ id, scene }`. At most six sizes (`RESIZE_MAX`), a size twice once, custom sides whole numbers of 16-8000 (`resizeSizes()`).

## Why not "As new scenes"?
- The plan's screen offered "As new scenes" too, but a canvas's scenes share one board size (`image-editor-scenes.md`); the step asked the architect in its PR.

## Where is the code and the test?
- `assets/js/pg_image_editor_resize.js` (part `resize`, bundled after `starts`), wrapping `Editor.panels.artboard` to put its section after Canvas size; labels `pg_image_editor_strings_resize()`; rules under "Resize to other sizes" in `pg_image_editor.src.css`.
- `tests/image_editor_resize_test.php`: sizes, names, the axis rule, a square made a banner (title, corner logo, picture and shape backgrounds, bar, group, small text, scenes), a turned shape, Make on an editor with a stubbed request. The panel was not run in a browser.

Related: docs/memory/ui/image-editor-artboard.md
