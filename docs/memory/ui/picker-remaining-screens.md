---
name: picker-remaining-screens
description: The product builder's pictures, the contact photo and the theme designer's background file on the picker (PK9, #8904); the image window editor_select_image.php deleted with its add_file.php return
module: editor, picker
---

## What opens when a screen asks for pictures?
- `software_image_picker({initialize: true, SingleImage, file_input_name})` (`backend.src.js`) opens `pgEditorPicker.pictures()` (`pg_ckeditor_picker.js`): kind `image`, several pictures unless `SingleImage`; with an Unsplash key a small dialog asks "From the site" or Unsplash first.
- Each pick comes back as before through `window.software_image_picker({return: true, image_name, file_id, …})`, looked up at call time, so the product builder's variant strips (`product_builder.js`, its wrapper) still get theirs. An Unsplash photo is its address with `file_id` 0 (the contact save reads `image_url`, `contact-form-checks.md`).
- Without the picker on the screen it says "The picker is not loaded on this screen."; no window opens any more.

## Which screens print the picker?
- The product builder prints `pg_editor_bridge_script()` (picker, bridge settings, Unsplash); `pg_contact_form_picture_card()` prints it for Add Contact and Edit Contact. It is printed once per request.
- The label editor's Image element (`LabelEditor` in `backend.src.js`, Browse) uses `pgEditorPicker.pictures()` itself: one picture, its name or the Unsplash address; without the picker it keeps the local file reader.

## What about the theme designer?
- Background › File of a module is `pg_theme_designer_background_field()`: `pg_picker_field('public_image', …)`, a picture every visitor may open, as a theme background is served to everyone; the value stays the file name. `theme_designer.php` prints `pg_picker_script()`. The old list held design images only; the field offers every public picture.
- The screen is unlinked (`panel-without-jquery-ui.md`); nothing was opened in a browser.

## What went with the image window?
- `editor_select_image.php`, its test, `add_file.php`'s `uploadreturn` redirect back to it, `filebrowserImageBrowseUrl` and the browse window size of `_pg_wysiwyg_file_properties()`; Cleanup lists the file for sites that still carry it. The tests that read its source lost those cases.

## What is left for PK9b?
- The image editor's From the site lists (after V10, #8854, which holds the image editor's files) and the Workspace's "from the files" picker.

Tests: `tests/editor_bridge_js_test.php` (`test_editor_bridge_picture_items`, `test_editor_bridge_image_window_deleted`), `tests/theme_designer_entries_test.php`, `tests/editor_file_properties_test.php`. Related: picker-editor-bridge.md, picker-dialog.md
