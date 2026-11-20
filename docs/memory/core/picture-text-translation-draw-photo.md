---
name: picture-text-translation-draw-photo
description: A photo with text layers drawn per language from the Artifact Canvas Languages list (PT4b): the browser's overlay and parts, the server drawing on the base row as a save does, format kept
module: picture_translation
---

## Which photos are drawn?
- A photo whose newest version holds its document over a base row (a save in the Artifact Canvas, `ui/image-editor-versions.md`) and whose file still holds that save's pixels: `pg_picture_tr_live_doc()` kind `photo` (`pg_picture_tr_doc_kind()`). A base the retention removed leaves nothing to draw on: no Languages button.
- The list answer adds `kind`, `base` (the base row's version id) and the file's `name`.

## What does the browser send?
- `M.versionOpenDoc()` then `M.languageDoc()` and the same fit as a canvas (`core/picture-text-translation-draw.md`). The painter loads the base row's picture (action `picture`) for the colour layer and a matte's guide.
- Beside the stamp: the overlay (`drawOverlay()`), the canvas around the picture (`M.saveLayout()`), redaction and area masks, `export: png` for a JPEG with a see-through erase, the colour layer when the server has no Imagick: what a save's hooks send.

## What does the server draw?
- `pg_picture_tr_draw_photo()`: the recipe from the live document (`pg_image_edit_validate()`), never a document or base from the request; the pixels of the base row the document names; the first frame of an animated photo.
- The save's own steps, split out of `pg_image_editor_save()` for both: `pg_image_editor_photo_parts()` (extend, overlay, redaction, area masks into temporary files) and `pg_image_editor_photo_pixels()` (colour layer or Imagick, engine, export, camera details). The save reads its redacted mark from `$edit['redact']`.
- The picture keeps the photo's format (`summer-de.jpg`), or the export's; a later draw in another format makes a new row and deletes the old one.

## What was checked?
- `tests/picture_translation_test.php` (`test_picture_translation_photo_kinds`). In a sandbox: a JPEG with two text layers saved by the editor, Draw all giving JPEGs on the base, German at the 70% floor marked Look by hand. Not run: a redacted or erased photo, a server without Imagick.
