---
name: image-accent-colour
description: get_dominant_area_color(), the accent colour the Edit file and order screens take from a picture: palette images and transparent pixels.
module: image
---

## What does it return?
- The most frequent colour of a sample of about 50 x 50 pixels, each channel rounded to a step of 32, as `#rrggbb`; `#9696968e` (grey) when the file cannot be read or has no visible pixel.
- Callers: `edit_file.php` (the file's picture), `view_order.php` (the contact picture).

## How are palette images and transparency read?
- A palette image (GIF, 8-bit PNG) answers `imagecolorat()` with a palette index: the colour is looked up with `imagecolorsforindex()`. Reading the index as RGB gave every such picture `#000000`.
- Fully transparent pixels (alpha 127, or a GIF's transparent index) are skipped: a logo on a transparent background gives the logo's colour, not black.
- PR #6055. Tests: `tests/image_dominant_colour_test.php`; the WebP cases in `tests/image_webp_read_test.php` (`gd-webp-read.md`).
