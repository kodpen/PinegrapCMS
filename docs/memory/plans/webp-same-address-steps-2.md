---
name: webp-same-address-steps-2
description: Steps W7-W9 of plans/webp-same-address.md (#4677): a 320 px rung for panel thumbnails, Workspace previews from the derivative cache, the master file and shrink on upload
---

## Which steps are there?
- All three S (under 400 product lines); none holds W1-W6. Claim as `[plan:webp-same-address] W<n>`.

- W7 (S) Panel thumbnails. `free` after W3.
  - A 320 rung appended to `pg_image_derivative_widths()` (a copy's name carries its width, so no existing copy is invalidated; the site's `srcset` offers it too). The File Manager tiles and list, the product table and the image pickers ask `?w=320` (with `f=webp` where `pg_image_delivery_settings()` says the server writes WebP) instead of the original. The warm-up pass (W3) makes the 320 copies of the newest files first; under Imagick a JPEG is decoded at a reduced scale (`jpeg:size`) before the resize.
  - Memory: `platform/image-derivatives.md` (the ladder), `ui/` file for the lists that changed.

- W8 (S) Workspace previews. After W7.
  - `ws_file_preview()`, `ws_file_preview_edge()` and the `data/temp/workspace_previews` sweep go; `api_resources.php` serves the copy through `pg_image_derivative()` with the file's `files` id (a Workspace upload is a `files` row stored under `FILE_DIRECTORY_PATH`), the asked edge rounded up the ladder (160 and 320 → 320, 640 → 960, 1280 → 1440, 1920 → 1920). The Workspace access check stays before any copy is sent. Clear caches empties these too.
  - Memory: `workspace/` preview file corrected, `platform/image-derivatives.md`.

- W9 (S) Shrink on upload. After W3.
  - The uploaded file stays the master; the WebP copy never becomes it. A setting "Shrink pictures larger than N px on upload" in Settings › Features › Image Optimization: `config.image_upload_max_dimension` SMALLINT UNSIGNED NOT NULL DEFAULT 0 (0 = off), migration step `11.0.142` with `install_add_column()`; a new install starts at 2560 (the install dumps carry it), a running site stays 0 until set. Same format, through the engine's resize with EXIF orientation applied; the product ceiling (`image_product_max_dimension`, 2400) stays its own; the heavy-images widget points to the setting. A file already smaller is stored byte for byte.
  - Memory: `platform/image-settings-and-product-upload.md`.
