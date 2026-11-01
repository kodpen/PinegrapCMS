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

## What comes later?
- E17 of `plans/image-editor-steps-2.md`: crop presets from the widget's ratio and the modal over the designer's own canvas.
