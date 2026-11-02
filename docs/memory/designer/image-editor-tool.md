---
name: image-editor-tool
description: The visual designer's "Edit image" on an image field: the field contract, replace versus copy, why the picture saves apart from the design
---

## How does the designer open the editor?
- `sdOpenImageEditor(fieldId)` (`style_designer.src.js`) reads the field's address, keeps only a file of this site, and calls `window.openImageEditor({ fileName, onSaved })`: the alias of the image editor's loader, which `includes/designer_screen.php` prints with `pg_image_editor_includes()` (`ui/image-editor-callers.md`). The file is opened by name through `image_editor_api.php`.
- The editor saves over the file; a file the operator may not write over (a design file for a manager, an animated picture) is saved as a copy (`platform/image-editor-save.md`).

## What is the designer-side contract?
- The contract is the FIELD, not the node. The button uses the same contract as `sdOpenImagePicker` ("which input am I filling"). Every field carrying an image address gets the button; the tool knows no node. That is why an image node's `src` and a background image work with one line each.
- Replace: the name does not change so the tree is untouched. The cache-buster is written ONLY to the rendered `<img>`; writing it to the tree would store an address that goes stale.
- Copy (`res.mode === 'copy'`): the input's `change` handler writes the new address; the toast says the design must be saved too.
- The image does not wait for the design to be saved: a file is an on-disk object, and tying a photo edit to an unrelated Save button is a way to lose the edit.

## Which crop ratio does the editor get?
- `_sdImageCropRatio(props)` for the Image element (its src field and its right-click entry; not a background or a bare `<img>`): its ratio box (preset `16x9`, or the Sizing panel's custom ratio switched on as `56.25%` or `calc(9 / 16 * 100%)`), else whole-number width and height props. `_sdRatioOf()` names it (`40:21`, a near small fraction, or `2.5:1`). The editor's crop panel shows it first and enters Crop on it (`ui/image-editor-crop.md`).

## How does the canvas show a picture saved over?
- `_sdEditedImages` (path → cache key) holds the files saved over during the visit; `_sdApplyImageStamps()`, run at the end of every `renderCanvas()`, puts `_pgv=<key>` on `<img>`/`<source>` `src` and `srcset` candidates and inline background images of those paths (same-origin, decoded). Before (#4609) the key went on the drawn `<img>` once, so the next redraw showed the browser's week-old copy again. The bare address is also fetched with `cache: 'reload'` for the preview and other tabs. Tests: `tests/image_editor_designer_test.php`.

## Does the editor sit over the designer?
- Yes: it is a `<dialog>` opened with `showModal()`, the top layer, above the designer's chrome, canvas iframe and any open designer dialog; no z-index is involved.
