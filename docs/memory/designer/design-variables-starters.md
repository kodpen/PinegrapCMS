---
name: design-variables-starters
description: How many starter blocks (Blocks tab, core and family files) and palette components (Bootstrap group) read a design's variables and how many carry fixed values, counted at the variables' close-out
---

## What is the rule?
- Starter blocks and palette components use the design's variables, never literals (`design-variables-overview.md`). Colours, fonts, weights and corners reach them through Bootstrap classes and `--bs-*` tokens, which the variables stylesheet drives when a value differs from its default (`design-variables-model.md`).

## How many follow it? (counted 2026-11-25, development after #11660)
- 315 checked: 130 core blocks and 145 family-file blocks of `_sdBlockPaletteItems()`, and the 40 items of the Components tab's Bootstrap group. None reads a `--pg-var-*` token directly.
- 307 carry no fixed colour: Bootstrap classes, `--bs-*` tokens or `rgba(var(--bs-…-rgb), a)`. No block or component has a fixed length in an inline style.
- 7 carry a fixed black scrim over a picture (`rgba(0, 0, 0, a)` gradients or fill in their block rules): core `hero-image-bg`, `spotlight-media`, `image-carousel`, `news-lead-grid`; families `dealership-showroom`, `dealership-ways`, `launch-camera`.
- 1 carries fixed colours as content: `variant-picker`'s swatch dots (inline `background-color` per choice, the product's colours).
- `#000` in `marquee-logos` and `podcast-episode` is a mask's alpha, not a colour.

## What do they not read yet?
- Spacing: section padding is Bootstrap's `py-*` classes in every block, and scale, base unit and section rhythm drive no theme token, so none of the 315 follows the type scale, base unit or section rhythm.
- The sketch builder's and the document page's body sections write `var(--pg-var-space-section, 3rem)`, the stylesheet's token (`pg_design_variable_token()` turns underscores into hyphens), so a changed section rhythm reaches them (#11699; before it they wrote `space_section` and the fallback always applied).

## How was it counted?
- Every item built under Node with `_sdDragTree()` (the harness of `tests/designer_block_sketch_test.php`), its nodes' inline styles and colour props read, and its rules from `_SD_BLOCK_CSS_NEEDS` read with `var(…)` taken out. A count again uses the same reading.
