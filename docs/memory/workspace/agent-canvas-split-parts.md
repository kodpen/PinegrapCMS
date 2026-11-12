---
name: agent-canvas-split-parts
description: Layers "split": a text as a text layer, a site file's picture as a picture layer (canvas_split_parts.php): what converts, fonts, placement, which files
---

## When does a text become a text layer?
- In a split (`agent-canvas-layers.md`) a top-level `text` becomes `type: 'text'` when the editor's text layer can hold it (`ws_canvas_split_text_layer()`); otherwise it stays an svg layer as in #8799. Built in #8823.
- Kept as svg: a `class` (style rules reach it, its look is unknown), a stroke, a fill that is no plain colour (gradient, none, alpha), a font the editor does not offer, `rotate`, `textLength`, `dx`/`dy` or several x on the text, a `tspan` with any attribute but x, y, dy or with markup inside, words beside tspans, a size in em or %, a stretching, mirroring or skewing transform, a filter, clip path or mask.
- Lines: tspans one under another at the same x become lines joined by a new line; their distance gives `lineHeight` (times the size), else 1.25.

## Which fields does it get?
- `text`, `font`, `size` (times the transform's scale), `weight` (bold 700, a number snapped to 300, 400, 600, 700, 900), `italic` (italic, oblique), `align` from `text-anchor`, `color` #rrggbb (#rgb, rgb() and basic names read), `spacing` in em from `letter-spacing` px, `opacity` from opacity times fill-opacity, `rot`; the rest the editor's `M.textDefaults()` (`bg` '', `pad` 0.3, `radius` 0.16, no outline or shadow).
- Fonts (`ws_canvas_split_font()`): the first family of the list the editor offers. Generic names and common system faces map to its stacks (sans, serif, mono, hand, display); a family of `pg_google_fonts.js` (read from the file, `ws_canvas_split_google_fonts()`) or of the site's styles (`style_custom_fonts`, `ws_canvas_split_site_fonts()`) is `g:<family>`.

## Where is the text box?
- The editor's layout (`M.textLayout()`) with widths estimated at half the size a letter: the first baseline sits at `(lineHeight - 1) / 2 + 0.8` sizes below the top, so it lands on the SVG's y; the aligned side sits on the SVG's x. The editor fits the width to the real font when it draws the layer, keeping the aligned side (`textFit()`). The test runs `M.textLayout()` under Node against the box.
- An `add` with a frame moves a text or picture layer by its box and scales a text's size with the frame's height (`ws_canvas_split_into()`).

## When does a picture become a picture layer?
- A top-level `image` (or the one of a saved motion group) whose href names a file the agent may use: `type: 'image'`, `src` the site address (`PATH` + name), `fileId`, the box the file fills by `preserveAspectRatio`'s default (fitted, centred), stretched with `none`; another value stays svg.
- The sanitizer drops every address, so `ws_canvas_split_mark_pictures()` runs on the raw SVG first: a resolvable href becomes `#pgfile-<random token>-<n>`, which the sanitizer keeps; a mark the agent forged has no file and stays an empty svg layer.
- Files (`ws_canvas_split_file()`): an address of this site, absolute or from `PATH`, query dropped, no folder; a picture file (png, jpg, gif, webp, avif, svg) that a message of the channel carries, or, with the `files:read` scope, one in a public folder and no attachment (the files API's reach). Pinegrap AI's tools get the channel's files only. Any other address is dropped by the sanitizer, as before.
