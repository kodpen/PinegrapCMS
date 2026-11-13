---
name: image-gd-palette-png
description: A palette PNG (PNG-8) through the GD image engine: a turn, flip or crop in the Design Canvas, the rotate button or Optimize writes it back as a palette PNG, pixel for pixel, while it fits 256 colours
module: image
---

## What does the GD engine write for a palette PNG?
- `pg_process_image_with_gd()` (`includes/image_engine.php`) notes whether the source PNG decoded as a palette (PNG-8, and greyscale, which GD reads as a palette). Every step works on a truecolor copy; when the result is written as PNG, `pg_image_gd_exact_palette($image, 256)` turns it back into a palette picture with exactly its pixels, each colour with its alpha (the PNG writer puts alpha in tRNS).
- It counts colours with their alpha and gives up past 256, past 16,777,216 pixels or on a palette picture; the file is then truecolor RGBA as before. A resize blends colours, so it usually stays truecolor.
- GD's `imagetruecolortopalette()` is not used: it reduces colours even when they fit.
- A truecolor source PNG is never turned into a palette.

## What was the symptom? (#9118)
- Under GD a flip and Save on a 214 KB PNG-8 gave 440 KB (colour type 6), and Optimize opened on "+%128" for a 56 KB PNG-8. The estimate action and the save go through the same engine, so both show the palette size now.
- Imagick keeps its own path (`colors`, 256 colours, `ui/image-editor-export.md`).

## And the JPEG half of #9118?
- A JPEG starts at the lower of 92 and its own saved quality: `image-editor-jpeg-start-quality.md`.

Tests: `tests/image_gd_palette_png_test.php`.
