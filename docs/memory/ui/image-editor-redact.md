---
name: image-editor-redact
description: The image editor's redaction (E10): layer fields, box and brush areas, the preview filters, the masks a save sends, the server's step 4 under Imagick and GD, metadata, versions
module: image_editor
---

## What is a redaction layer?
- `type: 'redact'` in `doc.layers`: `mode` pixel, blur or solid, `strength` 1-100 (unused by solid), `shape` box (a turnable box) or brush (`trace` normalised in the box as the pen's, `size` its width in turned-space units; `M.brushLayer()`, `M.brushPoints()`).
- The Redact tool draws one by dragging: Area box or brush, Method, Brush size, Strength; a press on a selected area moves it (the tool is a select tool). The annotate mini bar's redact defaults to blur.

## How does the stage show it?
- `redactNode()`: the picture again under an SVG filter, cut to the area by a mask (`maskUnits="userSpaceOnUse"`, a white rect or round-capped stroke). Pixelate: one sample per block (feFlood, feTile, composite, feMorphology dilate), the grid from the crop box's corner. Blur: feGaussianBlur. Solid: `#111317`. The picture carries the core's colour filter inside it.
- Sizes: `M.redactSizes(strength, longer side)` = `pg_image_edit_redact_sizes()` before the output scale (block 3.5 %, sigma 2 % of the longer side at full strength); `tests/image_editor_redact_test.php` holds them equal.

## What does a save send and the server do?
- The layer paints nothing into the overlay. `redactMasks()` sends `redact: [{mode, strength, x, y, mask}]`: per visible area a PNG of its output box (`M.redactRegion()`), opaque where the area is.
- The save hook sends them for replace, copy and `url` (save.url, e.g. the Workspace): `pg_image_editor_render()` takes and burns them in as a save does, so a redacted picture saved on another endpoint no longer shows what was covered (E10b, #4745).
- `pg_image_editor_take_redactions()` checks them before any pixel work (at most 50, known method, JSON numbers, whole pixels, a PNG inside the output) and writes the masks beside the file; the engine gets `edit['redact']`, the masks are removed on every path.
- Step 4 runs after the colour step and the vignette, before the overlay: `pg_image_edit_redact_imagick()` (crop the box grown by 3 sigma or out to whole blocks, scale down and sample up, or blur; DSTIN the mask; composite) and `pg_image_edit_redact_gd()` (blur by shrinking, two 3 x 3 blurs and drawing back; the mask's opacity blends per pixel). Layers above a redaction in the list are still drawn over it, the overlay being last.

## What happens to metadata and versions? (#4203)
- An edit writes no metadata: Imagick strips all but a non-sRGB ICC profile, GD writes none; a test feeds a JPEG with EXIF through a redacted save.
- A redacted replace writes its version row with `redacted = 1`, `source = 'redact'`; earlier versions, the original included, stay under the normal retention, readable only through the editor API behind `check_edit_access()`. A restore of a redacted version keeps the flag. Save as a copy leaves the file as it is. A redacted Workspace replace (`pg_image_editor_render()` answers `redacted`) writes its `ws_file_edits` row with `redacted = 1` (11.0.144); `ws_file_history()` lists that earlier version only for the editor (`ws_file_edit_visible()`, owner #4783, E10c #4844); the file stays in the file manager under its folder's access. The panel's warning says so (the prototype's "older versions are deleted" was replaced by the owner's decision).
