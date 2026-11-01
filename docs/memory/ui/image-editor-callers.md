---
name: image-editor-callers
description: Who opens the image editor and how (#4609): the openImageEditor alias, data-pg-image-edit buttons, the live site's copy into content, Workspace's save.url, the redirect
module: image_editor
---

## Which calls open the editor?
- `pgImageEditor.open(options)` (`image-editor-modal.md`), or the older `window.openImageEditor(o)` that the loader of `pg_image_editor_includes()` maps onto it: `fileId`/`fileName` open the file through the API and save over it (`allowReplace: false` saves a copy); `saveUrl` becomes `save: { mode: 'url', url, label: saveLabel }`; `onSaved` gets `result.file`; `onCancel` is `onClose`. `imageEditorAvailable()` is always true. Tests: `tests/image_editor_switch_test.php`.
- A file known only by name (a page's picture, the designer's field) is opened with `{ action: 'open', file_name }`; names are unique in `files`.

## Which screens call it?
- File Manager (`view_folders.php`): `openImageEditorFor()` by id, "New image" a blank 1000 px white canvas saved with `save.mode 'new'` (E12 replaces it with the New image dialog). Its SVG "Save a copy as PNG/JPG/WebP" is not the editor: it uploads the drawn picture beside the SVG through `explorer_upload`.
- `edit_file.php`, `edit_design_file.php`, `view_files.php`, `view_design_files.php`, the dashboard's heavy-images widget (`widget_18.php`): a button with `data-pg-image-edit="<file name>"` and `pg_image_editor_includes()` beside it. The loader's click handler opens the file and reloads the page after a save, first fetching the bare address with `cache: 'reload'` (files are cached for a week).
- Designer: `designer/image-editor-tool.md`. Workspace: below.

## How does the live site's edit button work?
- In edit mode `get_page_content.php` prints the loader (signed-in editors only: owner answer 4 of `plans/image-editor.md`). The buttons of `add_edit_button_for_images()` (`includes/fn/content.php`) and of the inline editor (`frontend.src.js`, hand-edited `frontend.min.js` twin) carry `data-pg-image-edit`, `data-pg-image-object`, `-object-id`, `-column`.
- A replace changes every page using the file. A copy (the user may not write over it) is put into the content by API action `content` `{ object_type, object_id, column, from: name, to: id }`: `pg_image_editor_content_places()` names table, id and column, `pg_image_editor_content_allowed()` keeps the old screen's per-type checks for the user role (3), `update_image_in_content()` rewrites the `<img>`. A converted common region is left alone.

## How does Workspace save?
- `save.url` mode posts the save body (doc, overlay, redact, colour) to `workspace_file_save.php?message=<id>&as=replace|send`; `pg_image_editor_render()` draws it from the message's file (first frame, the file's own format) and the result goes to `ws_message_file_replace()` / `ws_message_file_send()` as before. The workspace screen prints the loader; `loadImageEditor()` is `pgImageEditor.load()`. A canvas drawn there (New canvas) sends its PNG instead and opens again with its layers: `image-editor-workspace.md`.

## What happened to the old editor?
- Removed in #4609: `assets/lib/image_editor/` (Pintura, 2.3 MB), `assets/js/image_editor_modal.js`, `image_editor_save.php`, `get_image_editor_includes()`; `clean_up.php` lists the three paths. `image_editor_edit.php` only redirects to the file's folder in the File Manager with `#pg-image-edit=<name>`, which the loader opens.
