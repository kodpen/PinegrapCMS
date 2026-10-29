---
name: upload-keyboard
description: Files and Design Files upload screens from the keyboard: the Choose files button beside the Dropzone area, why outside it, the remove link shown on focus
---

## How does a keyboard user add a file on Upload File?
- `add_file.php` and `add_design_file.php` print `<button type="button" id="pg_upload_choose">` (Select File(s)) after the `.dropzone_previews` drop area and pass Dropzone `clickable: [".dropzone_previews", "#pg_upload_choose"]`. The drop area is a plain div and Dropzone 5.9.3 gives its hidden input `tabindex=-1`, so before this Tab skipped from the page to the folder card and no file could be added.
- The button is `hidden` in markup; Dropzone's `init` shows it. Without Dropzone the `.fallback` file input stays and the button stays hidden.

## Why is the button outside the drop area?
- `.dz-message` hides once files are queued, so a button inside it would vanish; a button inside a clickable element and also listed opens the dialog twice.

## Why does a queued file's remove link show on focus?
- Dropzone builds it as a focusable `<a class="dz-remove">`; `backend.src.css` showed it only on `.dz-preview:hover` (`opacity: 0` otherwise). It now also shows on `.dz-preview:focus-within` and `.dz-remove:focus-visible`.

Pinned by `tests/upload_keyboard_test.php`. Product screen photo chips: `panel-stepper-and-product-names.md`.
