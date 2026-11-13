---
name: image-editor-jpeg-start-quality
description: Decided, not built (#9118): a JPEG saved by the image editor or Optimize starts at the lower of 92 and the source's own quality, so a save is not heavier than its original
---

## What is decided?
- Architect (2026-11-13, asked in #9148 for #9118): a save is never heavier than its original unless the user raises the quality.
- For a JPEG source the start quality is the lower of 92 and the source's own quality, in a turn, a flip, a crop and in Optimize. The quality field opens on that value and the user may raise it.
- Where the source's quality cannot be read, today's 92 stays.
- Not built: the fix waits its turn as #9118. Today the editor encodes at 92 (`pg_image_editor_export_options()`), so a turn of a JPEG saved at about 70 grows 1.5 to 2.6 times.

## How is the source's quality read?
- Imagick: `getImageCompressionQuality()`. GD: from the file's quantisation tables.
- The reading is a pure function with a test: a JPEG saved at about 70 and turned once stays within a few percent of its size.

Related: `image-gd-palette-png.md` (the PNG half of #9118), `ui/image-editor-export.md`.
