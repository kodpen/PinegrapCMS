---
name: design-theme-product-looks
description: The looks and palettes for bold product pages (display, precision; onyx, silver, electric): values, why the names, the catalog test
module: design_themes
---

## Which looks were added?
- PR #8692: looks `display` "Display Headlines" (display weight 200, tracking -0.035em, large corners, soft depth) and `precision` "Precision" (radius 0, no shadow, weight 300, capitals at 0.14em); 14 looks, default still `modern-soft`.
- Not named `Display` or `Display Type`: both keys exist with another Turkish (Görünüm, Görüntüleme Türü), so the look took a narrower key ([[designer-turkish-terms]]).

## Which palettes were added?
- Palettes `onyx` (#0b0b0f / #0062ff), `silver` (#c4c8d0 / #16171c, dark text on primary like amber), `electric` (#0062ff / #a1a6b0); 22 palettes.
- Contrast (text on the colour; the colour as text on white / on the dark body): onyx 19.64 and 5.01; silver 10.58 (ink) and 17.90, its primary as text 5.11 on white; electric 5.01 and 7.26 (ink), its secondary as text 4.68 on white.

## Can a light palette colour draw a line on a white page?
- Silver's primary and Electric Blue's secondary are under 3:1 on white: their outline border, focus ring and checkbox tick are drawn in a readable shade ([[design-theme-pale-colours]]).

## What guards them?
- `tests/design_themes_catalog_test.php`: each look's file and known preview keys, no repeated colour pair, every palette file current (CI runs no `--check`), text on both colours at 4.5:1 in light and dark.

Related: docs/memory/designer/design-theme-look-and-palette.md
