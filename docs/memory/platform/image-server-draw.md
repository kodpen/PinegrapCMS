---
name: image-server-draw
description: The server draws an image editor document itself (Canvas Drawing on System Status): what it draws through Imagick, what it leaves to browsers, the probe, the guards and limits, no shell
module: image_draw
---

## What does the server draw?
- `includes/fn/image_draw.php` draws a canvas document as the editor's renderer would, for an agent's canvas no browser has drawn yet (`workspace/agent-canvas-picture.md`). Loaded where used (Workspace canvases, System Status), not by `functions.php` (CV6).
- Drawn: rectangles, ellipses, lines (fill, stroke, dashes, round corners, mirror); text in a font the server has a file of; Bootstrap icons; pictures (PNG, JPEG, WebP) from files the caller answers for; svg layers through librsvg.
- Anything else leaves the whole document to a browser: another layer kind or any unknown non-empty field (a shadow, a warp, a blend), a system font list (`sans`, `serif`: it differs per reader), a face the font link does not load (upright 300/400/600/700/900, italic 400/700), a letter the file lacks.
- Text only in Latin, Greek, Cyrillic and signs (`pg_image_draw_text_plain()`): no shaped or right-to-left script, no combining mark, no emoji. Roboto lacks `→`, so a text with it waits.
- Font files: `data/fonts/<family>-<weight>[i].ttf` (the editor's font folder), else the bundled Roboto in `assets/fonts/roboto/`. Nothing is fetched.

## How is an svg layer read?
- Only through ImageMagick's in-process librsvg reader, forced with `setFormat('RSVG')`. Never the `SVG` format (it starts a delegate, `inkscape`, through `/bin/sh`) and never `MSVG` (ImageMagick's own, draws badly).
- Each layer becomes one SVG of the artboard pixels it touches: the layer's matrix (`M.svgMatrix()` ported, `pg_image_draw_matrix()`) and a clip path of its own box, then `pg_svg_sanitize()`. A nested `<svg>` is stripped by the sanitizer, so the box is a clipPath.
- After the sanitizer, `pg_image_draw_svg_plain()` allows only shapes, gradients, clip paths, markers, `use` and style rules with plain selectors: no `<text>`, `<image>`, filter, mask, blend mode, `var()`/`calc()`, at-rule or pseudo-class.
- Debian/Ubuntu ImageMagick 6 and the project's Alpine image have no RSVG, so svg layers wait for a browser there.

## What does the probe do?
- `pg_image_draw_can()` answers shapes, text, icons, svg, pictures, paused, engine. It draws `pg_image_draw_fixtures()` (five 100 x 60 documents with the pixels to look at) once per PHP / Imagick / ImageMagick build and keeps the answer in `data/temp/image_draw.json`.
- Shapes must pass or nothing is drawn. Each other part passes on its own fixture; svg also needs RSVG in `Imagick::queryFormats()`.
- System Status, row Canvas Drawing (short label Canvas), reported and never scored, only with Workspace on: grey Off (no Imagick or shapes fail), yellow Paused, green On with a row per part (Drawn by the server / Left to browsers) and the engine.

## Which guards hold?
- `pg_image_draw_limits()` owns the numbers: artboard pixels, layers, a time budget looked at between layers, bytes and elements of one svg layer, letters, a picture's bytes and pixels, Imagick memory, map and disk, one thread.
- `pg_image_draw_guarded()` lowers Imagick's resource limits for the call and restores them. It writes a marker in `data/temp`; a marker older than `stale` means a drawing died (a crash, PHP's time limit) and the server's drawing pauses for `pause`.
- Pictures: read with an explicit format, one frame, an sRGB or no ICC profile, EXIF orientation applied.

## How close is it to the editor? (CV6)
- `image-server-draw-fidelity.md`.
