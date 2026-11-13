---
name: design-theme-dark-lines
description: A palette colour too dark for the dark surfaces draws its outline-button border, focus halo and range thumb lifted to 3:1, and the readable text shade is made for bg-body-tertiary in dark mode (#9057)
module: design_themes
---

## What went wrong in dark mode?
- Most palette colours are dark brand colours: under 3:1 against the dark body (#212529) and against `bg-body-tertiary` (#2b3035). The dark block of a palette wrote no `--bs-<role>`, so `.btn-outline-*` drew its border in the light colour: Fuchsia's secondary #312e81 measured 1.17:1 on the store template's account bar, and the language and cart buttons read as loose text (#9057).
- The readable text shade (`--pg-<role>-fg`, also `--bs-link-color` for primary) was made for 4.5:1 on the dark body only: 4.09:1 on the account bar.

## What does the generator write now?
- `_pg_theme_role()` (`includes/fn/design_themes.php`) measures the dark side against #2b3035, the lighter dark surface; what reads there reads on the body too.
- `--pg-<role>-fg` in the dark block: lifted until 4.5:1 on #2b3035 (the dark brand colours of the built-in palettes 4.51–4.97:1 there, 5.2–5.8:1 on the body).
- A colour under 3:1 on #2b3035 writes `--pg-<role>-line` and `-line-rgb` in the dark block, lifted toward white to 3:1 (`_pg_theme_readable($c, …, 3, true)`), and the colour itself (or the pale colour's readable shade) as the light block's line, so a light section inside a dark page does not inherit the dark line.
- A colour seen on both writes no line: today only Amber's primary. Silver's primary and Electric Blue's secondary keep the pale pair (`design-theme-pale-colours.md`).
- `bridge.css` was not changed: it already reads `var(--pg-<role>-line, var(--bs-<role>))` for the outline border, the disabled border, the focus halo and the range thumb.

## What was left as it is?
- Filled buttons and the outline button's hover and active fill keep the brand colour in dark mode (1.1–1.5:1 against the body for the darkest); their text reads on the fill. Changing the fill would repaint every running site's brand colour.
- `bg-body-secondary` (#343a40) is not measured: text there is about 4:1.
- A palette made in the editor gets the new pair the next time it is saved (`theme_palette_css`); the 22 shipped files are rebuilt with `php tools/build_theme_palettes.php`.

## What guards it?
- `tests/design_themes_catalog_test.php`: dark text at 4.5:1 on the body and on #2b3035, every line at 3:1 on both, the lifted line's RGB list, the light block keeping the colour itself.

Related: docs/memory/designer/design-theme-pale-colours.md, docs/memory/designer/designer-block-theme-surfaces.md
