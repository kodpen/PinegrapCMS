---
name: image-editor-svg
description: The image editor's SVG files: opened as layers on a transparent artboard, saved as SVG through the allowlist sanitizer pg_svg_sanitize()
module: svg_sanitize, image_editor
---

## How does an SVG file open?
- `pg_image_editor_open()` asks `pg_image_editor_svg_open()` first: a `files` row of type `svg` answers with its text through `pg_svg_sanitize()` (`svg` in the answer) instead of a picture address; the gates are `pg_image_editor_file()`'s (bin, ERP, `check_edit_access()`, design files only for roles 0-1), in `pg_image_editor_svg_file()`.
- `pg_image_editor_svg.js` wraps `openFile()`: the root's width, height and view box give a transparent artboard (`M.svgRoot()`, the view box fitted centred) and the canvas tools.
- Each top-level element is one layer of type `svg`: `kind` (path, rect, circle, ellipse, line, polyline, polygon, text, g, image; anything else `kept`, locked), `markup` (the element without its transform; root paint attributes copied down), `vb` (its own box from `getBBox()`), and a box `x, y, w, h, rot, mirror` from its transform (`M.svgPlace()`); `M.svgMatrix()` draws it back where it was. A skewed element keeps its transform inside a `g`.
- Definitions (`defs`, gradients, patterns, clip paths, masks, markers, filters, symbols, `style`) go into `doc.svgDefs`, drawn first on the stage by a `views` hook; title, desc and metadata are dropped.
- The panel: fill and outline colours on the top element (a painted group clears its children's), the text of a text element without `tspan`; a text edit measures the box again at the same scale.

## How is it saved?
- The part wraps `send()`: for an opened SVG, replace and copy post `svg_save` with the file's SVG (`M.svgFile()`: size, view box, the artboard's fill, `svgDefs`, each visible layer as its type's `node()` draws it). Pictures (`<image>` of picture and sticker layers) are fetched and embedded as data URLs (PNG, JPEG, GIF, WebP, AVIF); fonts stay by name.
- `pg_image_editor_action_svg_save()` sanitizes, refuses an empty result, writes through `pg_image_editor_put()` and records with `pg_image_editor_record()` (type `svg`, no pixel size).
- Not for SVG files: versions (the drawer reads pictures) and the raster export; a layer of type `svg` has no `paint()`, so it never reaches a PNG.

## What does pg_svg_sanitize() keep?
- It rebuilds the SVG element by element (`includes/fn/svg_sanitize.php`): only the listed drawing elements and their listed attributes; presentation properties as attributes or `style` declarations.
- `href` / `xlink:href` only to `#id` or `data:image/png|jpeg|gif|webp|avif;base64`; never `data:image/svg+xml`. A `use` with nothing left to point at goes.
- A value with an outside `url()`, a backslash, `<`, `expression(`, `javascript:` or `@import` is dropped; a style sheet with any of them, or an at-rule other than `@keyframes`, `@media`, goes whole.
- Refused whole (''): not a string, empty, over 5 MB, broken XML, a root that is not an SVG-namespace `<svg>`, any `<!DOCTYPE` / `<!ENTITY`. Comments, processing instructions, scripts, event handlers, `foreignObject`, `a`, animations and other namespaces never come through.
- Tests: `tests/svg_sanitize_test.php` (hostile samples), `tests/image_editor_svg_test.php` (the model's SVG section).
