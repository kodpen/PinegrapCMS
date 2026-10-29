---
name: unsaved-changes-guard
description: Which panel screens ask before the page is left with unsaved changes (code fields, product screen, offer and layout editors, settings dialog), how each knows, and the submit and Ctrl+S traps
---

## Which panel screens ask before leaving with unsaved changes?
- Every editable code field drawn by `pg_code_editor_attach()` (`includes/fn/editor.php`): theme CSS, JavaScript, design files, form list/item views, designer, dynamic, login and custom style forms, field and product group forms.
- The product create/edit screen (`assets/js/product_builder.js`).
- Their own guards: the offer editor (`offer_editor.js`), the layout editor (`edit_page_layout.php`), the File Manager's file window and the settings dialog.
- The browser shows its own text (`e.preventDefault()` + `e.returnValue = ''` on `beforeunload`), so no `lang()` key. Added for the leave-without-a-word loss on Cancel, a menu link, Back, reload or closing the tab.

## How does a code field know it changed?
- The script `pg_code_editor_attach()` prints takes `editor.getValue()` right after `attach()` (CodeMirror may normalise line ends, so not the textarea) and asks while the value differs.
- Left out: read-only fields (no guard printed), fields with no `form` (the layout editor guards its own), fields inside `#pg_settings_modal` (the dialog tracks its own changes), and an editor no longer in `window.pgCodeEditors` (released by `pgReleaseEditors()`).

## How does the product screen know it changed?
- On ready (after the other ready handlers and `renderMatrix()`) it keeps `pbState()`: `$form.serializeArray()` (uploaded photos are hidden inputs) and `collectVariants()` + `collectAttributeMeta()`.
- Rich text fields are compared by their editor (`CKEDITOR.instances[id].checkDirty()`, TinyMCE `isDirty()`) and filtered out of the field list on both sides when compared, since the editor may arrive after the snapshot.

## Why is the submit heard through jQuery and checked late?
- The Ctrl+S fallback (`backend.src.js`) calls jQuery's `form.submit()`, which runs jQuery handlers but no native `submit` listener; so the guard binds `$(form).on('submit')` and keeps the event.
- `beforeunload` skips only when that event was not prevented (`isDefaultPrevented()`): read then, every submit handler has run. A save the product screen refused (blank name or SKU) still asks.
