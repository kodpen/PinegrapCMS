---
name: design-theme-pale-colours
description: A palette colour too pale for a white page (Silver, Electric Blue's second colour): outline button border, focus ring, tick, radio dot and switch knob drawn in its readable shade
module: design_themes
---

## What is a pale colour?
- A palette colour under 3:1 on white (WCAG 1.4.11, lines and controls), worked out in `_pg_theme_role()` (`includes/fn/design_themes.php`). Among the built-in palettes only Silver's primary (1.68:1) and Electric Blue's secondary (2.44:1) are pale.
- On a white page such a colour cannot carry a line: an outline button looked disabled, a focused field showed no border, a white tick sat on silver at 1.68:1.

## What does the generator write for it?
- `--pg-<role>-line` and `--pg-<role>-line-rgb`: the readable shade (`--pg-<role>-fg`) in the light block, the colour itself in the dark block. A colour too dark for the dark surfaces writes the pair too, the other way round (`design-theme-dark-lines.md`).
- A pale primary also takes its focus ring (`--bs-focus-ring-color`) and focused border (`--pg-primary-focus-border`) from the line, and four marks from `_pg_theme_control_marks()` in the light block: `--pg-check-mark`, `--pg-radio-mark`, `--pg-dash-mark`, `--pg-switch-mark`, Bootstrap's drawings in the on-primary ink.
- A colour at 3:1 or more on white writes no light-block line of its own; Amber keeps its white tick (3.19:1).
- A palette made in the editor gets them the next time it is saved (`theme_palette_css`); a saved one keeps its old file until then.

## What does the bridge read?
- `bridge.css` reads `var(--pg-<role>-line, var(--bs-<role>))` for the outline button border and its disabled border, the focus halo of filled, outline and link buttons, and the range thumb.
- The checked checkbox, radio, indeterminate checkbox and switch read the marks with Bootstrap's white drawing as the fallback.
- A new component style that draws a palette colour as a line uses `var(--pg-primary-line, var(--bs-primary))`, never the colour alone.

## What did PR #8992 fix?
- Silver: tick, dot and knob 1.68 → 10.58:1; focused field border 1.21 → 1.91:1, its ring from the readable shade; outline button border 1.68 → 5.11:1. Electric Blue outline-secondary border 2.44 → 4.68:1.
- Precision alerts had one dark edge: the look set `--pg-alert-accent-color: currentColor` with `--pg-alert-accent: 0px`, and `base.css` paints the start edge in that colour at `max(border, accent)`. Precision no longer sets the colour; a look with no accent leaves it unset. Luxe has the same pair and was left as designed.

## What was left as it is?
- Onyx on a dark page: the filled button is 1.27:1 against the body, like Graphite's 1.15; a dark-body rule for fills would repaint running sites. Outline borders and text were lifted for the dark surfaces in #9057 (`design-theme-dark-lines.md`).
- A label inside a progress bar is clipped in thin-bar looks (Display 6px, Precision 2px); `.progress-bar.bg-secondary` text follows on-primary (Amber and Silver 1.01, Electric 2.44).
- Fixed light classes (`text-white`, `btn-outline-light`) on a light colour (Silver's white text 1.59–1.68:1) are a separate change (#8976).

## What guards it?
- `tests/design_themes_catalog_test.php`: the pale roles are exactly Silver/primary and Electric/secondary, each line is its readable shade at 3:1 on white with an RGB list, the focus border and ring are seen on white, a pale primary carries dark marks, and the bridge keeps Bootstrap's white as the fallback.

Related: docs/memory/designer/design-theme-product-looks.md, docs/memory/designer/design-theme-look-and-palette.md
