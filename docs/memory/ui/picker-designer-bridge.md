---
name: picker-designer-bridge
description: The visual designer's bridge to the picker (PK8): link, file and picture fields through pgEditorPicker, the source choice, Browse under widget page selects, the page type pane's picker fields
module: editor, picker
---

## What do the designer's fields open?
- `sdOpenFilePicker(fieldId, prop, kind)` (`style_designer.src.js`): kind `page` opens the page picker (short links among its entries, `picker-editor-bridge.md`), kind `file` the file picker; without a kind (a link field) a small dialog asks first: "A page or a short link" or "A file".
- `sdOpenImagePicker(fieldId)` and a double click on an Image item: the picture picker; with an Unsplash key the small dialog asks "From the site" or Unsplash first.
- The pick is written into the field as an address (`PATH` + the name encoded as `encode_url_path()`), with the input and change events the panel listens to. Cancel changes nothing.
- No window opens any more and no fake `CKEDITOR.tools.callFunction` is planted; `software_image_picker()` is not used by the designer.

## Where is the code?
- `pgEditorPicker` (`assets/js/pg_ckeditor_picker.js`, bundle `pg_editor_bridge.min.js`): `link()`, `page()`, `file()`, `image()`, `picture()`, each a promise of an address or null; `usable()` is false without `pgPicker`, `pgUI` or `#pg_editor_bridge_config`, and the designer then says "The picker is not loaded on this screen."
- `includes/designer_screen.php` prints `pg_editor_bridge_script()` (`includes/fn/editor.php`): the picker, the bridge's settings and the bundle. The panel header already gives the designer `pgUI`, Bootstrap 6 and the panel stylesheet the picker draws with.

## How do widget page selects get Browse?
- `_swPageOptions()` marks its first option `data-sw-page-none`; `_swPageBrowseDecorate()` runs with the properties panel's observer (`_sdPropsA11yInit()`) and puts a Browse… link under each such select once.
- Browse opens kind `page` on the selected page, the design's unsaved tabs as its own entries (`tab:<key>`, under "Not saved yet"). A pick the select does not list is added as an option, then chosen with input and change.
- The picker lists every page: a select kept to some widget types (sign-in, detail pages) still says under it when the chosen page lacks the widget.

## What about the page type pane?
- `page_type_pane.php` calls `pg_page_form_type_fields(…, true)`: page settings are picker fields, as on Create Page (`page-form-page-fields.md`); its save posts the fields' hidden inputs as before.

## What could not be checked?
- Nothing was opened in a browser.

Tests: `tests/editor_bridge_js_test.php` (`test_editor_bridge_designer_wired`), `tests/designer_video_item_test.php`. Related: picker-dialog.md, picker-pages.md
