---
name: image-editor-export
description: The image editor's export panel (E13): format, quality, longest edge, progressive JPEG, PNG colours, the estimate action, a format change saved as a copy, the optimise profile from the File Manager
module: image_editor
---

## What does the panel offer?
- `assets/js/pg_image_editor_export.js`, tool `export`: format (Original, then JPEG, PNG, WebP, AVIF as the server writes them), quality 40-100 for JPEG, WebP and AVIF (default 92, the save's own), longest edge (Original, 2560, 1920, 1280, 1080; never enlarged), progressive JPEG (on by default), 256 colours for a PNG (Imagick only), a fixed note that location and camera details are removed, and the size before and after.
- Opened from the save menu ("Export settings…") or alone with `profile: 'optimise'`: the File Manager's context menu entry of the same name (`view_folders.php`, `openImageEditorFor(item, null, 'optimise')`).
- The model's EXPORT section holds the maths (`exportRequest()`, `exportMode()`, `exportChange()`, `exportBytes()`); tested in `tests/image_editor_export_test.php`.

## How is the size after known?
- Action `estimate` (`pg_image_editor_action_estimate()`): the same recipe and settings through the engine, without overlay, redaction and (under GD) the colour step; answers `before`, `after` (size, width, height), `formats` and `colors`. Asked 450 ms after the last change and once each time the panel opens; an older answer is dropped.

## What does a save do with the settings?
- The save hook sends `export` only when something differs from the defaults; export settings alone make Save write (the core's "nothing changed" check asks `exportRequest()`).
- Server: `pg_image_editor_export_options()` refuses anything the panel would not send; another format turns a replace into a copy (#4676, answer 6a: the file keeps its format and address); the copy's name takes the new extension (`pg_image_editor_export_name()`) and its `files.type`. A copy takes no document and no pictures.
- `pg_image_editor_export_edit()`: WebP and AVIF through the engine's `format`, JPEG and PNG as `edit['type']`; `edge`, `colors`, `progressive` are edit steps in `includes/image_engine.php` (edge after the overlay and the extend, Imagick `resizeImage()`, GD `pg_image_edit_gd_edge()`).

## What is not done yet?
- Keeping camera details without the location, and converting to sRGB instead of keeping the profile: the engine strips all metadata and keeps a non-sRGB ICC profile (`platform/image-color-profile.md`). Plan line E13b.
