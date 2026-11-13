---
name: image-server-draw-fidelity
description: How the server's canvas drawing matches the editor's Chromium renderer: ImageMagick rounding and antialiasing traps, text placed letter by letter, the measured difference per document kind
module: image_draw
---

## How was it compared?
- Each document kind was drawn by the editor's painter in Chromium and by `pg_image_draw_document()`, and compared per pixel (mean difference on 0 to 255, premultiplied by opacity). ImageMagick 6.9.12 Q16; svg layers through `rsvg-convert` 2.58 standing in for librsvg, as this host's ImageMagick has no RSVG (CV6).
- Shapes 0.74, text 2.26 (letters within half a pixel), icons 0.28, pictures 0.25, a chart as one svg layer 0.17, the chart taken apart 0.26; the probe's own fixtures 0.06 to 2.6.
- Not compared: svg layers through Imagick's own RSVG reader (no such build here).

## Which ImageMagick traps does it work around?
- Text: ImageMagick rounds advances and glyph origins to whole pixels. The renderer reads the font's own tables (`pg_image_draw_font_read()`: hhea or OS/2 typo metrics, cmap format 4, hmtx), lays the text out as `M.textLayout()` does and annotates letter by letter on a tile drawn 2 to 4 times larger, then scales it down.
- Chromium's line box uses the font's ascent and descent rounded to whole pixels; widths are the sum of advances. Kerning (GPOS) is ignored: under a pixel.
- Outlines: ImageMagick places polygons by pixel middles. Half a pixel is taken off, fills are inset a quarter pixel and a stroke is drawn `max(s - 0.5, 0.75 s)` wide, which matches the canvas's coverage.
- Dashes follow the editor's own path order (`pg_image_draw_shape_points()`: a rectangle from its top edge clockwise, an ellipse from its left end down); a dotted line is drawn as circles, as near-zero dashes lose their caps on curves.
- Pictures: an upright picture (a multiple of 90 degrees) gets hard edges, as Chromium does not smooth the edges of an axis-aligned image; a turned one is smoothed.
- `type:hinting` has no effect; annotate text is literal (no `%` or `@file` escapes).

## What may still differ?
- A font in `data/fonts` that is not the face Google serves today (the bundled Roboto may be an older cut).
- Kerning, and the half-pixel placement of very small text.
- An svg layer that `use`s another layer's element is not drawn by either renderer.
