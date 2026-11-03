---
name: image-editor-svg-motion
description: Motion in a saved SVG (AN10): layer groups pgm-n, the numbers M.svgMotion() sends, the @keyframes style the server writes, reduced motion, what reopening keeps
module: image_editor
---

## What does the browser send?
- `svgText()` (`pg_image_editor_svg.js`) puts a moving layer's node in `<g class="pgm-n">`, n its place among the saved nodes, and `svg_save` carries `motion` from `M.svgMotion(doc, layers)`: duration, plays, and per layer its centre and 51 samples `[percent, opacity, x, y, scale, rot]` from `M.motionAt()` (presets and keyframes), a frame equal to both neighbours dropped.
- A wipe, a drawn stroke or typed letters become the share shown, as opacity: a style sheet cannot cut them. Redactions never move.

## What does the server write?
- After `pg_svg_sanitize()`, `pg_image_editor_svg_motion_add()` gives the root an id when it has none (`pgsvg-` and ten hex of the SHA-1) and puts one `<style>` first, a CDATA section (its `>` stays raw), from `pg_image_editor_svg_motion_css()`.
- Numbers only, each checked by type and range (duration 1-60 s, plays 0-1000, 2-101 frames, percents rising, opacity 0-1); anything else and no style is written at all, so nothing the browser sent reaches the file as text. Never the browser's CSS, no SMIL, no script.
- Per layer: `#<root> > .pgm-n` (the root's own children only: a stale class on a nested group never matches) with `transform-box: view-box`, origin 0 0 and `animation: <root>-pgm-n <s> linear <plays|infinite> both`; each frame's transform is the stage's: translate to the moved centre, rotate, scale, translate back (the same functions in every frame, so they interpolate one by one).
- All inside `@media (prefers-reduced-motion: no-preference)`: a reader who asks for less motion sees every layer at rest.

## What happens when it is opened again?
- The sanitizer drops the style (an at-rule), and a save without motion writes none.
- A top-level `g` whose only class is `pgm-n`, with no attribute but that and a transform, holding one element (`M.svgMotionGroup()`), opens as that element, the group's transform before its own: a text stays a text. Before this a reopened moving layer came back as a `g` layer, and a second save nested `pgm-n` in `pgm-n`, so `#root .pgm-n` moved it twice (review of #6349).
- A saved `svg` layer drops any `pgm-n` from its own class (`M.svgMotionClass()`), so a stale name never meets a later save's rule.

## How was it checked?
- `tests/image_editor_svg_motion_test.php`: the samples, the style and its refusals, the style placed in a sanitized root.
- By hand in Chromium: at half time the layer is moved, turned and scaled about its centre; under reduced motion nothing animates.
