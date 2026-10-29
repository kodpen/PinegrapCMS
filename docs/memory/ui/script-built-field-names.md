---
name: script-built-field-names
description: Dialogs and fields drawn by script after load name themselves: unique title ids, label for, aria-label from the screen's label map, Escape kept (#3029)
---

## Why does a script-built field need its own name?
- `pgInjectAriaAndRequiredMarkers()` (`backend.src.js`) binds a label without `for` to its next field only at DOMContentLoaded. Markup a script draws later (a dialog body, an inline edit box) is never reached, so a box would be named only by its placeholder or not at all.
- So the script writes the name itself: a `<label for>` when a label is drawn, else an `aria-label` from the screen's own label map, never a literal English word.

## Which ones carry it now?
- File manager (`view_folders.php`): the delete dialog names `folder_delete_title`; both confirm labels (type the name, type the word) point at `folder_delete_name_input`; the Access drawer label at `access_type_select`; the one-time link box `sl_address` is `L.link_once_title`; inline rename is `L.rename`; the quick-edit cell is the column's name (`quickEditLabel()`: `L.price`, `L.quantity`, `L.short_description`).
- Pintura dialog (`image_editor_modal.js`) names `pgImageEditorTitle`.
- Orders list Paraşüt export dialog (`view_orders.php`) names `xlsl_setup_title`.
- Image editor Save Option dialog (`image_editor_edit.php`) names `save_option_title`; its format select is `lang('File Format')`.
- Page Designer (`page_designer.js`): the System Tags dialog names `editor_region_title`; its four close buttons read `labels['Close']`, which `page_designer.php` passes in its label map.

## What rules came with it?
- A dialog title id is unique: the two `exampleModalLabel` copies were renamed.
- A dialog whose close loses nothing keeps Escape: no `data-bs-keyboard="false"`. The Save Option dialog keeps only its static backdrop.

Test: `tests/screen_dialog_names_test.php`.
