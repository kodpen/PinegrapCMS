---
name: image-editor-export
description: The Design Canvas's export panel (E13, E13b): format, quality, edge, progressive JPEG, PNG colours, camera details, sRGB, the estimate action, another format as a copy, the optimise profile
module: image_editor
---

## What does the panel offer?
- `assets/js/pg_image_editor_export.js`, tool `export`: format (Original, then JPEG, PNG, WebP, AVIF as the server writes them), quality 40-100 for JPEG, WebP and AVIF (opens on the estimate's `start_quality`: 92, or a JPEG's own quality when lower, `platform/image-editor-jpeg-start-quality.md`), longest edge (Original, 2560, 1920, 1280, 1080; never enlarged), progressive JPEG (on by default), 256 colours for a PNG (Imagick only), metadata (below), and the size before and after.
- Opened from the save menu ("Export settings…") or alone with `profile: 'optimise'`: the File Manager's context menu entry of the same name (`view_folders.php`, `openImageEditorFor(item, null, 'optimise')`).
- The model's EXPORT section holds the maths (`exportRequest()`, `exportMode()`, `exportChange()`, `exportBytes()`); tested in `tests/image_editor_export_test.php`.

## How is the size after known?
- Action `estimate` (`pg_image_editor_action_estimate()`): the same recipe and settings through the engine, without overlay, redaction and (under GD) the colour step; answers `before`, `after` (size, width, height), `formats` and `colors`. Asked 450 ms after the last change and once each time the panel opens; an older answer is dropped.

## What does a save do with the settings?
- The save hook sends `export` only when something differs from the defaults; export settings alone make Save write (the core's "nothing changed" check asks `exportRequest()`).
- Server: `pg_image_editor_export_options()` refuses anything the panel would not send; another format turns a replace into a copy (#4676, answer 6a: the file keeps its format and address); the copy's name takes the new extension (`pg_image_editor_export_name()`) and its `files.type`. A copy takes no document and no pictures.
- `pg_image_editor_export_edit()`: WebP and AVIF through the engine's `format`, JPEG and PNG as `edit['type']`; `edge`, `colors`, `progressive` are edit steps in `includes/image_engine.php` (edge after the overlay and the extend, Imagick `resizeImage()`, GD `pg_image_edit_gd_edge()`).

## What happens to metadata and the profile? (E13b)
- The location is always removed (a fixed, checked box). Every save strips all metadata as before; "Remove camera details" (checked by default) is offered only from a JPEG into a JPEG (`M.exportCamera()`). Unchecked, `pg_image_editor_export_camera()` takes the Exif block of the picture as it was opened (before any colour layer), keeps a whitelist through `pg_image_exif_camera()` (make, model, software, dates, artist, copyright, exposure, lens) and writes it with `pg_image_jpeg_embed_exif()`. Never carried: the GPS IFD, IFD1 (its thumbnail shows the unedited, unredacted picture), the maker note, the orientation (the pixels are upright).
- "Convert colours to sRGB" shows only when the estimate answers `srgb`: Imagick built with lcms (`pg_image_imagick_converts()`) and the sRGB profile dompdf ships (`pg_image_srgb_icc()`). The engine's `srgb` step converts a non-sRGB profile with `profileImage()` before the strip; otherwise the profile is kept (`platform/image-color-profile.md`). GD cannot convert, so it never offers it.
