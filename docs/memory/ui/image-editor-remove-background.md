---
name: image-editor-remove-background
description: The image editor's Remove background (E28c, #6948): the subject a layer of its own over the picture erased whole; the erased part's fills (colour, blur, a picture from Files) in the mask step
module: image_editor
---

## What does Remove background do?
- A button beside Select subject in the Select area panel (`areaRemoveBackground()` in `pg_image_editor_area.js`). It takes the subject of a subject op in the selection (its soft edge), or finds it first (a lone rectangle is the box, as Select subject does; edge 4). `areaEraseRest()`, one undo step: the subject becomes a picture layer of its own, the cut-out Cut to a layer makes (`areaCutout(rings, soft)`), over the picture erased whole (one Erase layer of the frame, `M.areaRest([], M.pictureBox())`, locked); the selection goes and the erased area's panel opens with the Select tool. A press on the subject takes the piece, so a drag moves the subject alone and leaves nothing of the picture behind; a drag on the background moves nothing. Before (the owner's report, 2026-11-05) only the rest was erased and the erased area was selected: a drag moved the mask and the background came back, the subject stayed. Refine first with the brushes (`image-editor-subject-brushes.md`): the piece is cut once and does not follow later strokes.

## What can fill the erased part?
- The Erase layer's panel, "Fill the erased part": See-through, Colour (`fill`), Blur (`blur`, units, 1-100, first 1 % of the picture's long side) or Picture (`backdrop: { src, fileId, name }`, a picture from Files through the Import list, `openImportFiles(pick)`; shown only where the editor has its API, or when a layer already has one). One of the three fields at a time (`eraseSet()`); every Erase layer has these, not only Remove background's.
- Stage: Blur is the picture with the document's colour filter under an SVG `feGaussianBlur` of `blur` (the subject's colour bleeds into the edge, as on the server); Picture is the Files picture over the picture's frame, `xMidYMid slice` (cover).
- Only a see-through erase counts as see-through (`eraseClear()`): a JPEG with a blur or picture fill stays a JPEG; Copy to a layer leaves such areas in.

## What does the save send and the server do?
- A mask entry's `blur` (radius in output pixels, `blur` × output scale, 0.5-250) or `picture` (the Files picture drawn by the browser over the frame and cut to the mask's region, a JPEG data: URL of exactly the mask's size; `areaBackdrop()`). A picture not loaded or not readable back sends none, so the area is see-through.
- `pg_image_editor_take_masks()` refuses a second fill, a blur out of range or not a number, a picture of another size or format; the picture file is dropped with its mask (`pg_image_editor_drop_redactions()`).
- Engine (`includes/image_engine.php`, `pg_image_edit_mask_backdrop()`): Imagick crops the box with a 3σ margin, scales it down by σ / 4 when σ is over 4, `blurImage()` with σ 4 there, scales back and crops (a full-size `gaussianBlurImage()` took 20 s on a 1440×960 photo at σ 14 and over 200 s at 4000×3000 and σ 40; scaled 0.14 s and 1.2 s, within 3 / 255 of it), or reads the picture; then DSTIN the mask and OVER as a fill. GD: `pg_image_edit_blur_box_gd()` (the box with a 2r margin scaled down by r / 2, four passes of the 3 x 3 Gaussian, scaled back) or the picture, mixed per pixel by the mask; a picture covers see-through pixels, a blur keeps the alpha it blurred.

## How was it checked?
- `tests/image_editor_area_pixels_test.php`: the checks of the two fills; under GD (Imagick where loaded) a picture fills the masked half, a blur turns a sharp black-white edge into a ramp and leaves the rest.
- Browser (static page of the bundle, 1280×800, 390×844, 844×390): Remove background on a disc, the erased area within 0.3 % of the frame less the disc; Blur, Colour and a Files picture (set as the panel's pick does) on the stage and in `areaMasks()`; undo back to none. Not tried: a save through the API on a sandbox (Imagick is not on this machine).
