---
name: picker-editor-bridge
description: The rich text editor's bridge to the picker (PK7): CKEditor's Link dialog Page… and File…, short links in the page dialog, the Image dialog's Picture… and Unsplash…, the fallback without pgUI
module: editor, picker
---

## What do the editor's dialogs open now?
- CKEditor's Link dialog: Browse Server is labelled Page… and opens `pgPicker.open({kind: 'page'})`, with the short links as the dialog's own entries under "Short links" (extra, `picker-pages.md`); a File… button beside it opens kind `file`. The pick goes into the URL field as an address (`PATH` + the name encoded as `encode_url_path()`), the link type set to URL.
- The Image dialog: Browse Server is labelled Picture… and opens kind `image`, on the picture already in the field; an Unsplash… button beside it opens the Unsplash dialog when the site has an Unsplash key.
- The Upload tabs stay CKEditor's own (`editor_add_file.php`, `rich-text-editor-files.md`).
- The previous editor (TinyMCE 3) keeps its own lists (#8835).

## Where is it?
- `assets/js/pg_ckeditor_picker.js` (window.pgCkeditorPicker, and window.pgEditorPicker for the designer) and `assets/js/pg_unsplash.js` (window.pgUnsplash), one bundle `assets/js/pg_editor_bridge.min.js`.
- It hooks `CKEDITOR.on('dialogDefinition', …, priority 20)`, after the filebrowser plugin (10), and takes over the `browse` button's click; a second button is spliced into the list that holds it.
- `pg_editor_bridge_script()` (`includes/fn/editor.php`) prints `pg_picker_script()`, the `#pg_editor_bridge_config` JSON (endpoint, path, texts, `short_links`, `unsplash`) and the bundle, once, from `get_wysiwyg_editor_code()` behind the same gate as Browse and Upload (role 1–2, or role 3 with a folder to edit).

## Who gets short links and where do they come from?
- `picker.php?kind=page&request=short_links` answers `pg_picker_short_link_entries()`: every named short link not in the Recycle Bin, first 2,000 by name, as entries `{value: name, text, type_label: "Short link", note: "→ destination"}`. A user (role 3) gets none, as the old link window gave them none: short links are a manager area.
- The destination text is `pg_picker_short_link_destination()` (page, group, product with `?t=` tracking code; an address as given).

## What happens without the panel's scripts?
- Where `pgPicker` or `pgUI` is missing (a site page with a rich text field), the bridge does nothing: the Link dialog has no Browse Server (the link window and `filebrowserBrowseUrl` were deleted in PK8b), and the Image dialog's opens `editor_select_image.php`. When PK9 deletes that window (`software_image_picker()` still opens it), `filebrowserImageBrowseUrl` in `_pg_wysiwyg_file_properties()` goes with it.

## What could not be checked?
- Nothing was opened in a browser: the dialogs' layout and the focus between CKEditor's dialog and the picker's `<dialog>` were not seen. The picker's Escape is taken on `window` in the capture phase, so it never reaches CKEditor's dialog.

Tests: `tests/editor_bridge_js_test.php` (Node), `tests/picker_test.php` (short links). Related: picker-dialog.md, rich-text-editor-files.md
