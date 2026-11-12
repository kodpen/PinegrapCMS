---
name: image-panel-thumbnails
description: Panel lists draw pictures from their 320 px WebP derivative (pg_image_panel_thumb_url(), thumbUrl()): which lists and files, 320 kept out of page srcset, warm-up pass, jpeg:size
module: image
---

## Why?
- File Manager tiles, the product table and the image pickers loaded each original (a camera photo is megabytes) to draw a box 36 to 116 px wide.

## Which address does a list ask for?
- `pg_image_panel_thumb_url($url)` (`includes/image_engine.php`, pure; JS twin `thumbUrl()` in `view_folders.php`): JPEG/PNG get `w=320&f=webp`, WebP `w=320`; GIF (may move), SVG and anything else, or an address with `w=`/`f=` already, stay as they are. A `#fragment` stays last.
- Used by: File Manager and Catalog Manager tiles and list (files, product and group pictures; ERP pictures keep their own address), the product table cell (`pg_product_image_cell()` in `includes/fn/ecommerce.php`), `editor_select_image.php`, the picker (`pg_picker_file_details()`). Quick Look, the preview pane and the lightbox keep the original.
- A refused or busy derivative sends the original, so a tile never breaks for it.

## Why does the page markup not use 320?
- `pg_image_derivative_widths()` gained 320 (a copy's name carries its width: nothing stored was invalidated); the pages' srcset reads `pg_image_derivative_page_widths()` (480 up), so page HTML did not change and no page needs a rebuild. A request for `?w=1` to `?w=320` now gets 320.

## What made the copies faster?
- The warm-up's thumbnail pass makes them ahead of the File Manager, newest first (`image-derivative-upkeep.md`).
- Imagick reads a JPEG that is to be shrunk with `jpeg:size` = twice the target's longest edge on both sides (`pg_image_jpeg_size_hint()`): libjpeg decodes at 1/2 to 1/8 scale. Not for an edit (it needs the full frame); GD has no such read.

Related: image-derivatives.md, image-same-address.md
