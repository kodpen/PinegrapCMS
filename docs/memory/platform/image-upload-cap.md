---
name: image-upload-cap
description: Upload cap (config.image_upload_max_dimension, step 11.0.142): pictures over N px scaled down on upload in their own format; the doors, 2560 on a new install, off on a running site
module: image
---

## What does it do?
- "Shrink Pictures Larger Than" in Site Settings › Features › Image Optimization: an uploaded picture whose long edge is over the value is scaled down to it through the engine's resize (`pg_process_image_file()` with `max_dimension` and `image_resize_quality`), in its own format, turned upright from its EXIF orientation. The engine never writes a heavier result.
- A picture at or under the cap is not touched: `pg_image_upload_cap_options()` measures it (`getimagesize()`) before the engine runs, so the cap never re-encodes quietly. Formats: jpg, jpeg, png, gif, bmp, webp; TIFF and animated pictures stay as they are.
- The uploaded file stays the master; the WebP copy is a derivative (`plans/webp-same-address.md`, question 9).

## Where is it stored and what are the defaults?
- `config.image_upload_max_dimension` SMALLINT UNSIGNED NOT NULL DEFAULT 0, step `11.0.142`; 0 is off. A running site keeps storing what it stored before; a new starter site gets 2560 from `install/index.php` after the upgrade (the starter dumps predate the column). A restored backup keeps its own value.
- Read in `pg_image_settings()` as `upload_max_dimension` (behind `pg_schema_has()`), clamped by `pg_image_upload_cap_clamp()`: 0, or 200–20000. The save writes it only when the column exists and the field was posted; an empty box is off.

## Which uploads apply it?
- `pg_file_store_upload()` (Upload File screen and its cloud picker) for every upload that is not a product photo, the editor's Image › Upload (`editor_add_file.php`) and the file manager's drag-in upload (`pg_explorer_upload_image_options()`, also under "convert to WebP").
- Not under the product profile or the file manager's optimize: the product ceiling (`image_product_max_dimension`) is its own setting.
- Not the external API's `files` upload: it is documented as byte for byte unless `image_profile=product`.
- A scaled picture's row is written with `optimized = 1` and its new `image_width/height`, and the Site Log gets "image (…) was scaled down on upload to W x H pixels".

## Where is it pointed to?
- The dashboard's File Management widget (`widget_18.php`) links to the card while the cap is off and the "Needs optimizing" list has rows, for roles that may open the settings (role < 3).

Tests: `tests/image_upload_cap_test.php`.
