---
name: image-editor-crop
description: The image editor's crop tool: geometry in the document, the rotated space, cover scale, crop mode with its undo and Cancel, ratios, handles, turns and flips, shape, output size, keys
---

## Where does the geometry live?
- `doc.geometry`: `rot` (0/90/180/270), `flipX`, `flipY`, `straighten` (degrees, -45 to 45), `crop` (a box in the rotated space, or null for the whole picture), `cropShape` (`rect`, `rounded`, `circle`), `output` ({w, h} or null for the crop size).
- The rotated space is the picture after its quarter turn and flips (`flipRot()`); layers and the crop box live there. `pictureMatrix(doc)` takes the picture's pixels into it: turn and flips, then the straightening around the centre, scaled by `coverScale()` so the crop box stays covered.
- The frame shows `viewRect()`: the crop box, or the whole rotated space while cropping; the picture is one `<img>` at its natural size under `matrix(scale(k) · translate(-view) · pictureMatrix)`. A rounded crop draws 12 % of the shorter side as the frame's radius, a circle 50 %.

## How do turns and flips move things?
- `turnDocument(doc, ±1)` turns what is on screen a quarter, clockwise for 1: under a single flip the picture's own `rot` goes the other way. `flipDocument(doc, 'x'|'y')` mirrors what is on screen. Both go through `reorient()`, which carries the crop box and every layer (`mapLayer()`: line ends, point lists, path nodes, boxes; `text` and `sticker` move only their centre) and swaps a set output size.

## How does the crop tool behave?
- Picking Crop remembers the document and the history's depth (`enterCrop()`); every change inside is an undo step of its own (a handle or box drag is one). Apply, Enter or picking another tool keeps it (a box as big as the picture becomes `crop: null`); Cancel or Escape brings the remembered document back and drops the steps taken since.
- The crop box (`.pgie-cropbox`, in the stage over the frame) dims the rest, shows thirds and eight handles; `resizeCrop()` keeps a ratio from the opposite corner (an edge keeps the other axis centred) and never leaves the picture; dragging inside moves it (`moveCrop()`).
- Ratios: free, the picture's own, 1:1, 4:3, 3:2, 16:9, 4:5, 9:16 (`fitRatio()`: the largest box of that shape around the current centre). Circle starts square. The panel's Reset sets the orientation, straightening, crop, shape and output back.
- Output size: width or height typed, the other follows the crop box's ratio, and follows it again when the box changes. The size under the file name is `outputSize()`.
- Panel figures use the panel's decimal separator (`pgImageEditorConfig.decimal`). After a panel redraw takes away the focused button, the stage takes the focus back so Ctrl+Z keeps working.
