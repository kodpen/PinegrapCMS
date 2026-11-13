---
name: picker-overview
description: The one picker for a file or a page as a whole: what it is, the decisions that bind it, its two faces and bridges, which file holds each part, what stays outside it, how a new kind is added
module: picker
---

## What is it?
- One picker chooses a file, a picture or a page on every screen of the panel, the editor and the designer. It follows the File Manager (tree, tiles or list, search, sort, Upload). Built 2026-11 (#8515 to #9256).
- One registry `pg_picker_kinds()` and one endpoint `picker.php` (`includes/fn/picker.php`), one script `pg_picker.js`, two faces: the dialog `pgPicker.open()` and the field `pg_picker_field()`.

## Which decisions bind it?
- Owner (2026-11-10, `workflow/decisions-made-product-42.md`): one modern picker replaces every separate way of choosing a file or a page; ready for other records later (a product, a quote, a shipping method, a contact); the software's own editor reaches it through a bridge. No "Choose from File Manager" button.
- Owner (2026-11-10, #8514): the drawn screens (#8511) are approved; an archived page, like a binned one, is shown marked and cannot be chosen.
- Owner (2026-11-12, `decisions-made-product-48.md`, #9070): the dialog opens on All, every item of the kind.
- Stored values never changed: every field stores what it stored before (a file name, a file id, a page id, an address), with no schema or data step.
- `view_folders.php` has no pick mode and is never framed as a picker: it is a very large file most sessions edit, and a framed screen cannot be a field's dialog on a phone. The picker shares the File Manager's tree data, rights, tile styles, 320 px pictures and sort rules instead.

## Where is each part?
- Registry, kinds, endpoint requests, access, value states: `picker-registry.md`. All (every item, paged): `picker-all-list-server.md`, `picker-all-list-dialog.md`.
- The dialog, its keys and the phone sheet: `picker-dialog.md`; Upload, Recently used, several picks: `picker-upload.md`; pages, type chips, archived and binned marks, a caller's own entries: `picker-pages.md`.
- The field (combobox with the chosen value, Browse, clear): `picker-field.md`; page settings on Create Page and Edit Page: `page-form-page-fields.md`; page fields on the other screens: `picker-page-fields-screens.md`.
- Bridges: CKEditor's Link and Image dialogs `picker-editor-bridge.md`; the visual designer `picker-designer-bridge.md`; product builder pictures, contact photo, theme designer file `picker-remaining-screens.md`; Design Canvas From the site and the Workspace's file attach `picker-site-lists.md`.
- A site page in Edit mode (decided, unbuilt): `site-editor-picker-frame.md`.

## What stays outside it?
- The short link list's quick edit File cell searches `file_picker_search.php` (`file-picker-search.md`); list quick edit keeps its own page lists (`picker-page-fields-screens.md`).
- Folders: `select_folder()` stays a select of the folder tree (`settings-folder-pickers.md`); a `folder` kind is a step of its own when the owner asks.
- TinyMCE 3 keeps its own lists under `assets/lib/tiny_mce_3_5_10/` (#8835). Upload File's cloud picker stays an upload source (`platform/cloud-file-picker.md`); the picker's Upload stores through the same `pg_file_store_upload()`.
- Record searches (users, New cart quote's products and contacts, audience rules) stay server-search comboboxes until a kind takes them over.

## How is a new kind or screen added?
- A kind is one row in `pg_picker_kinds()`: its source, filter, role, what a pick stores, and whether it has a tree, pictures and Upload; a typed page kind is a row of `pg_picker_page_type_kinds()`. A later record kind (a product, a contact) brings its own search and list, without a tree.
- A screen prints `pg_picker_field($kind, $name, $value)` and `pg_picker_script()` once; an editor or screen family gets an adapter that calls `pgPicker.open()` and writes the pick where its host wants it. The picker knows no editor.

Tests: `tests/picker_test.php`, `tests/picker_js_test.php`, `tests/file_picker_search_test.php`.
