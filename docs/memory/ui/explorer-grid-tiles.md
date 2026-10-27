---
name: explorer-grid-tiles
description: Explorer grid tiles (view_folders.php, catalog and files): the tile size scale, picture vs glyph, the missing-picture plate sized from --pg-tile-icon, the narrow pill it fixed (PR #2200)
---

## Where is a grid tile drawn?
- `view_folders.php` serves the file explorer and the catalog (`view_product_groups.php` sets `$explorer_area = 'catalog'` and includes it). `itemIconHtml()` draws the tile's picture or glyph; the CSS sits in the same file.
- Tile size comes from three variables on `#explorer_app`: `--pg-tile` (column), `--pg-tile-thumb` (picture box height), `--pg-tile-icon` (glyph size), set per size step (small, medium, large, xlarge). Size new tile parts from these, never in fixed pixels, so a tile stays in proportion at every step.

## What does a tile whose picture will not load show?
- `thumbBroken()` swaps the `<img>` for `.item-thumb-fallback` (an image glyph on a grey plate) inside the same `.thumb-wrap`, which keeps the corner badge.
- The plate is a square of `var(--pg-tile-icon) * 4 / 3` (64 px at medium), the width of a folder or group glyph, with the glyph at `.56` of the tile icon size, centred.

## Why not `width: 100%`?
- `.thumb-wrap` is a shrink-to-fit inline block; once the picture is removed it collapses to its content, so a percentage width resolved to the glyph and the plate drew a narrow, tall pill (about 36 × 84 px) beside 64 px group glyphs. Seen on product groups whose `image_name` points at a missing file (PR #2200). The list view keeps its own 36 px rule.

Related: docs/memory/ui/panel-css-tokens.md, docs/memory/ui/panel-screenshots.md
