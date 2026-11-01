---
name: image-upload-cap
description: Upload size cap (config.image_upload_max_dimension, 11.0.142, #4700): library uploads above N px shrunk in their own format; 0 off, 2560 on a new site; the doors
module: image
---

## What does it do?
- A picture uploaded to the file library whose longest edge is above `config.image_upload_max_dimension` px is shrunk to it in place, in its own format, through `pg_process_image_file()` with `max_dimension` (EXIF turned into pixels; written only when lighter, invariant 1 of `image-processing-engine.md`). A picture at or under the cap, or not a picture, is stored byte for byte: `pg_image_upload_cap_apply()` checks `getimagesize()` first, so the cap never recompresses what it does not shrink.
- Why (owner, #4677 question 9): a phone or camera original should not become the master at full size; the uploaded (shrunk) file stays the master that derivatives and the WebP copy are made from, never its WebP copy.

## Which values?
- `SMALLINT UNSIGNED NOT NULL DEFAULT 0`; 0 is off. `pg_image_upload_cap_clamp()` holds any other value between 200 and 20000 (read in `pg_image_settings()` as `upload_max_dimension`, saved by `features.save.php`).
- Off on a running site (the step adds 0); `install/index.php` sets 2560 after the upgrade only for a starter site, as `max_image_preview_large`. A restored backup keeps its value.
- Quality of the shrunk file: `image_resize_quality`. Not flagged `optimized`: only the size went down.

## Which doors apply it?
- `pg_file_store_upload()` (Upload File screen, picker dropzone, cloud picker) when the product profile is not in force; the product profile keeps its own ceiling (`image_product_max_dimension`).
- API `files.php` upload without `image_profile=product`; the File Manager upload (`includes/explorer/handle_upload.php`) when Optimize is off (Optimize uses the product ceiling), before a WebP conversion; `editor_add_file.php`.
- Not applied: design files (`add_design_file.php`), the old TinyMCE 3 plugin, Workspace, form and ERP attachments.

## Where is it shown?
- Site Settings › Features › Image Optimization ("Shrink Pictures On Upload Above"), hidden until the column exists (`pg_schema_has()`).
- The dashboard's file widget (`widget_18.php`) says whether it is on and links to the card, for roles that may open Settings (≤ 2).

Related: image-settings-and-product-upload.md, image-processing-engine.md
