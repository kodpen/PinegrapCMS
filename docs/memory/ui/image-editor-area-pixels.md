---
name: image-editor-area-pixels
description: What an image editor selection does to pixels (E27b, #6678): Erase inside and Adjust inside layers, Keep only, Copy to a layer, the masks a save sends, the engine's mask step under GD and Imagick
module: image_editor
---

## Which layers does a selection make?
- `type: 'erase'` (optional `fill` '#rrggbb'; see-through without) and `type: 'adjust'` (`adjust`, `filter` as the document's), both `shape: 'area'` of `M.areaLayer()` with the selection's `feather`, as Redact the selection makes. They hide, undo and delete as layers but are the picture's own (`M.pictureArea()`): no press on the stage, no move or stretch of their own, they move and scale with the picture (`image-editor-area-cut.md`); they paint nothing into the overlay, and the stage shows them as the fill, a checkerboard, or the picture again under the area's own colour filter over the core's (`maskNode()` in `pg_image_editor_area.js`, the redact part's shape helpers through `X.areaShapes`).
- A new one is selected with the Select tool, so its panel shows: Erase has its fills (see-through, colour, blur, a picture from Files: `image-editor-remove-background.md`); Adjust inside is the Adjust panel scoped to the layer (`adjustTarget()`: the sliders and filters write to the one selected adjust layer, no vignette or sharpening).

## What do Keep only and Copy to a layer do?
- Keep only, one undo step: the crop becomes the selection's box (inside the old crop; an output size scales with it) and an erase layer covers the rest of the box (`M.areaRest()`; none for a rectangle). A straightened picture or one on a canvas keeps its crop, which would move it under its layers: only the rest is erased. The selection goes.
- Copy to a layer: the picture inside the outline (feathered), with the document's colour step, as a PNG picture layer in place named "Cut-out" (at most 4096 px a side); redactions and see-through erases are cut out of it, so the overlay never shows what they hide. A save keeps it as a file like any picture from this computer. A picture that cannot be read back shows a note.
- Cut to a layer, the name "Erase inside" and where an area layer takes a press: `image-editor-area-cut.md`.

## What does a save send and the server do?
- `masks: [{ op: 'erase'|'color', x, y, mask, fill?, adjust?, filter? }]`, one per visible layer in the layers' order, a PNG of its output box like the redaction masks (`areaMasks()`); for replace, copy, `url` and the animation base. `pg_image_editor_take_masks()` checks them (at most 50, whole pixels inside the output, a known op, a `#rrggbb` fill, adjustments in range) and computes each colour step (`pg_image_editor_mask_color()`); a colour area without a change is left out. Masks are dropped with the redactions on every path.
- The engine's mask step runs after the colour step, sharpen and vignette, before the redaction (which burns in over it): `pg_image_edit_masks_imagick()` (DSTOUT for see-through; a fill or the coloured crop DSTIN the mask, laid OVER) and `pg_image_edit_masks_gd()` (per pixel by the mask's opacity; the colour per pixel by `pg_image_edit_color_pixel()` with a gamma table made once per area, as `applyColor()`: a colour area over a whole 2400×1600 picture took 3.6 s without it, 2.6 s with it, the rest being GD's pixel reads and writes).
- A format without alpha erases to the fill, else white. A JPEG with a see-through erase saves as a PNG copy beside it (the save hook sets `export.format` png and mode copy; a format chosen in the export panel wins). Erase marks no version as redacted.

## How was it checked?
- `tests/image_editor_area_pixels_test.php`: the mask check, one pixel's colour step, erase on a PNG and a JPEG with and without fill, Adjust inside only under the mask, under GD and (where loaded) Imagick. `tests/image_editor_area_test.php`: `areaRest()`.
