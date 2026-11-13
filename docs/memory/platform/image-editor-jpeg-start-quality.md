---
name: image-editor-jpeg-start-quality
description: A JPEG turned, flipped or cropped (Design Canvas, rotate buttons, Optimize) starts at the lower of 92 and its own quality, so a save is not heavier (#9118)
module: image
---

## What is decided?
- Architect (2026-11-13, asked in #9148 for #9118): a save is never heavier than its original unless the user raises the quality.
- For a JPEG source the start quality is the lower of 92 and the source's own quality, in a turn, a flip, a crop and in Optimize. The quality field opens on that value and the user may raise it. Where the source's quality cannot be read, 92 stays.

## How is it built?
- `pg_image_jpeg_quality($data)` (`includes/fn/image.php`, pure): the luminance quantisation table (DQT table 0, 8 or 16 bit) against the standard table scaled as libjpeg does for each quality 1-100; the closest sum wins (order-free, so zigzag does not matter). 0 when the bytes are not a JPEG or hold no table. Used under GD and Imagick alike, so both read the same number.
- `pg_image_jpeg_start_quality($path, $ceiling = 92)`: reads the first 256 KB; the lower of the ceiling and the reading, never below 40 (the panel's lowest); the ceiling when nothing is read (PNG, WebP, a missing file).
- Design Canvas save and the `estimate` action: `pg_image_editor_export_start()` sets the start on the picture as it was opened (the version base included) when the request chose no quality. The estimate answers `start_quality`.
- Export panel: `M.exportStart()`, `M.exportDefaults(start)`, `M.exportRequest(ex, own, start)`; the panel takes `start_quality` from the first estimate and moves the slider to it until the user moves it. Only a quality other than the start is sent.
- The File Manager's rotate (`includes/explorer/handle_files_media.php`) and Edit File's rotate buttons (`edit_file.php`) encode at `pg_image_jpeg_start_quality()` too.

## What was the symptom?
- A flip and Save of a JPEG saved at about 70 grew 1.5-2.6 times (written at 92), and Optimize opened on +61 % for a 46 KB JPEG.

Tests: `tests/image_jpeg_quality_test.php` (readings 10-100, refusals, a turn of a JPEG at 70 within 10 % of its size), `tests/image_editor_export_test.php`. Related: `image-gd-palette-png.md` (the PNG half), `ui/image-editor-export.md`.
