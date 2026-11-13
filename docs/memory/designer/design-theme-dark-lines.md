---
name: design-theme-dark-lines
description: Dark mode lines and text: a dark palette colour lifted to 3:1 for outlines, focus and range (#9057); the readable text shade for bg-body-tertiary; danger red as #ea868f (#9097)
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

## What does dark mode do with danger red (#9097)?
- Palettes write no danger role, so Bootstrap kept #dc3545 for `.text-danger` and `.btn-outline-danger` in dark: 3.41:1 on the body (My Account's overdue total, "Sign out of all devices").
- `bridge.css` sets `--pg-danger-fg-rgb` to Bootstrap's dark danger text shade #ea868f under `[data-bs-theme=dark]` (6.1:1 on the body, 5.3:1 on #2b3035) and back to the red under `[data-bs-theme=light]`; `.text-danger` and the outline button's text, border and disabled state read it. `--bs-danger-rgb` is untouched, so `.bg-danger`, `.btn-danger` and the outline hover fill keep the red under white text.
- `bridge.css` loads only with a look or a palette: a design with a colour mode and neither keeps the light red.

## What guards it?
- `tests/design_themes_catalog_test.php`: dark text at 4.5:1 on the body and on #2b3035, every line at 3:1 on both, the lifted line's RGB list, the light block keeping the colour itself.

Related: docs/memory/designer/design-theme-pale-colours.md, docs/memory/designer/designer-block-theme-surfaces.md
