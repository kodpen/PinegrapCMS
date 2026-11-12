---
name: designer-palette-drawings
description: Plan (owner 2026-11-11): one Components tab with a Blocks switch, a small drawing per look, a drawing and a short description for every ready block and component
---

## What is it for?
- The Visual Page Editor shows what a look, a ready block or a component looks like before it is chosen: a small drawing and one line of description each, as the Components tip card does today (`_sdPalTipHTML()`).
- The Blocks list lives in the Components tab behind a second-level switch ([[designer-palette-tabs-and-blocks]]).

## What are the steps?
- About 1,000 product lines each; a step's PR changes only its own line.

- P1 (M, done #8656): one Components tab, a Components / Blocks switch (`_sdPalSwitchHTML()`, `_sdPalSwitchWire()`), the rail without a Blocks button, the list remembered. Test `tests/designer_palette_switch_test.php`.

- P2 (S, done #8731): look drawings. Settings > Theme cards already draw `_sdThemeLookDemo()`; the Theme pane's head row shows a brush icon, the template chooser (`pg_designer_template_theme_picker()`) text pills. One small SVG (about 48×32: a card in the look's corner, outline and shadow, a heading bar in its weight, a button in its button corner) from the look's `preview` row (`pg_design_looks()`), JS and PHP twins, in both places; a custom look draws its base. Test: every look has every `preview` key, both twins give the same SVG.

- P3 (M, done #8699): block drawings from the tree ([[palette-block-sketch]]). `_sdBlockSketch(tree)` draws a block as picture 1 does (heading dark bar, text grey lines, picture or card grey box, button accent pill, columns side by side) from the tree its `build()` returns, cached per `componentType`, shown in the tip card of every core and family block; the band walk of `PGProtoHotspots.sketch()` ([[prototype-thumbnails]]) is the model. Test: Node draws every core and family block, none empty.

- P4 (M, done #8758): descriptions of the 127 core blocks, one `_sdT()` line each in the block info map `_sdBlockInfo()` read by `_sdPalTipHTML()` ([[palette-block-info]]); Turkish with `tr_add.php`. Test `tests/designer_block_info_test.php`: fails for a block of `_sdBlockPaletteItems()` without a line.

- P5 (M, done #8777): family blocks A to H (appstudio to hours, 56 blocks) but `florist`: a `desc` per item in the family's `items` ([[designer-block-family-files]]), read by `_sdBlockDesc()`; the P4 test reads families, with a list of the families still without lines.

- P6 (M, waits for P5): family blocks L to Z (launch to zoo), as P5, and `florist` (held by an open PR during P5); it empties `designer_block_info_test_undescribed_families()`.

- P7 (M, free): Components items with a description but no drawing: HTML elements (section, article, main, header, footer, aside, nav, div, pre, input, checkbox, radio, textarea, select), Content (heading, paragraph, span, image, divider, spacer, custom HTML, SVG, custom PHP, carousel, messages, breadcrumb, variant attribute, applied offers, link, icon). Drawings in `_sdPalDrawing()`.

- P8 (M, waits for P7: same map): the Form fields (27) and Regions (13) drawings, and the 26 Lists in a record rows, which have no tip card at all (key `loop_area` is one for all): a drawing and a line each. Test: every Components row has a card with a drawing.

## How does it meet designer-bold-pages?
- B1, B4, B5, B7 and B9a add blocks: P3 draws them unasked; the P4/P5 test fails until the later of the two PRs writes their line.
- B8 adds the looks `display` and `precision`: P2 draws them from their `preview` row, so B8 gives every key.
- B6 is an option of a block: nothing to draw.

## What waits for a decision?
- Nothing: the three wishes and their places are the owner's (2026-11-11). Palettes show their two colours in both choosers already, so no palette step.
