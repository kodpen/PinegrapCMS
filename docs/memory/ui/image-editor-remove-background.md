---
name: image-editor-remove-background
description: The image editor's Remove background (E28c, #6948): subject kept, the rest erased; the erased part's fills (colour, blur, a picture from Files) in the mask step
module: image_editor
---

## What does Remove background do?
- A button beside Select subject in the Select area panel (`areaRemoveBackground()` in `pg_image_editor_area.js`). With a subject op in the selection it erases what lies outside the selection's rings within the picture's frame (`M.areaRest()` over `M.pictureBox()`), with the subject's soft edge as `matte`; without one it cuts the subject first (a lone rectangle is the box, as Select subject does) and erases the rest with the default edge (4). One undo step: the erase layer goes in, the selection goes, the layer's panel opens with the Select tool. Refine first with the brushes (`image-editor-subject-brushes.md`): the layer keeps fixed rings and does not follow later strokes.

## What can fill the erased part?
- The Erase layer's panel, "Fill the erased part": See-through, Colour (`fill`), Blur (`blur`, units, 1-100, first 1 % of the picture's long side) or Picture (`backdrop: { src, fileId, name }`, a picture from Files through the Import list, `openImportFiles(pick)`; shown only where the editor has its API, or when a layer already has one). One of the three fields at a time (`eraseSet()`); every Erase layer has these, not only Remove background's.
- Stage: Blur is the picture with the document's colour filter under an SVG `feGaussianBlur` of `blur` (the subject's colour bleeds into the edge, as on the server); Picture is the Files picture over the picture's frame, `xMidYMid slice` (cover).
- Only a see-through erase counts as see-through (`eraseClear()`): a JPEG with a blur or picture fill stays a JPEG; Copy to a layer leaves such areas in.

## What does the save send and the server do?
- A mask entry's `blur` (radius in output pixels, `blur` × output scale, 0.5-250) or `picture` (the Files picture drawn by the browser over the frame and cut to the mask's region, a JPEG data: URL of exactly the mask's size; `areaBackdrop()`). A picture not loaded or not readable back sends none, so the area is see-through.
- `pg_image_editor_take_masks()` refuses a second fill, a blur out of range or not a number, a picture of another size or format; the picture file is dropped with its mask (`pg_image_editor_drop_redactions()`).
- Engine (`includes/image_engine.php`, `pg_image_edit_mask_backdrop()`): Imagick crops the box with a 3σ margin, `gaussianBlurImage(0, σ)`, crops back, or reads the picture; then DSTIN the mask and OVER as a fill. GD: `pg_image_edit_blur_box_gd()` (the box with a 2r margin scaled down by r / 2, four passes of the 3 x 3 Gaussian, scaled back) or the picture, mixed per pixel by the mask; a picture covers see-through pixels, a blur keeps the alpha it blurred.

## How was it checked?
- `tests/image_editor_area_pixels_test.php`: the checks of the two fills; under GD (Imagick where loaded) a picture fills the masked half, a blur turns a sharp black-white edge into a ramp and leaves the rest.
- Browser (static page of the bundle, 1280×800, 390×844, 844×390): Remove background on a disc, the erased area within 0.3 % of the frame less the disc; Blur, Colour and a Files picture (set as the panel's pick does) on the stage and in `areaMasks()`; undo back to none. Not tried: a save through the API on a sandbox (Imagick is not on this machine).
