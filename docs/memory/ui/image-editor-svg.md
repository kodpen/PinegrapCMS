---
name: image-editor-svg
description: The image editor's SVG open and save, and the allowlist sanitizer pg_svg_sanitize() every SVG passes through
module: svg_sanitize
---

## What does pg_svg_sanitize() keep?
- It rebuilds the SVG element by element (`includes/fn/svg_sanitize.php`): only the listed drawing elements (shapes, text, groups, gradients, patterns, clip paths, masks, markers, a few filters, `style`) and their listed attributes; presentation properties as attributes or `style` declarations.
- `href` / `xlink:href` only to `#id` or an embedded raster picture (`data:image/png|jpeg|gif|webp|avif;base64`); never `data:image/svg+xml`. A `use` with nothing left to point at goes.
- A value with an outside `url()`, a backslash, `<`, `expression(`, `javascript:` or `@import` is dropped; a style sheet with any of them, or an at-rule other than `@keyframes`, `@media`, goes whole.
- Refused whole (''): not a string, empty, over 5 MB, broken XML, a root that is not an SVG-namespace `<svg>`, or any `<!DOCTYPE` / `<!ENTITY` (entity reads and bombs). Comments, processing instructions, scripts, event handlers, `foreignObject`, `a`, animations and other namespaces never come through.
- Tests: `tests/svg_sanitize_test.php` (hostile samples).
