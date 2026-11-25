---
name: design-style-guide-model
description: The style guide sheet's model (SG1, 2026.11.0): variables, the stylesheet's text styles and shared components in, the six blocks out; the contrast pairs; what it leaves to the drawing
module: design_style_guide
---

## Where is the model?
- `includes/fn/design_style_guide.php`, pure (no database, no request, no file read beyond the look tokens `pg_design_variables_defaults()` already reads); tests in `tests/design_style_guide_test.php`. No screen: the sheet is drawn by SG2 of `plans/designer-style-guide.md`.
- `pg_style_guide_model($variables, $defaults, $css, $components)`: `$variables` is the design's variables model (`design-variables-model.md`) or null when it has no variables file; `$defaults` is `pg_design_variables_defaults()` of its look and palette; `$css` the design's own stylesheet text; `$components` its `shared_components` rows.
- Out: `has_variables`, `offer_start` (true without variables: the sheet shows the Theme pane's values under the same blocks and offers the start questions), `blocks` in the sheet's order: `colours`, `type`, `spacing`, `shape`, `controls`, `components`, each with a translated `title`.

## What does each block hold?
- Colours: `rows` (key, name, light, dark, token, custom), built-in colours in the variable list's order, then the designer's own; `pairs` (below).
- Type: `fonts` (heading and text font), `styles` (Display 1-6, Heading 1-6, Lead, Text, Small, then own classes), `scale` (XS, S, Base, L, XL, 2XL to 5XL: base size times scale to the step, whole px; same names as the computed rows of `designer-variables-drawing-computed-rows.md`).
- A style row: size and px (rem on a 16 px root, em on the base size), `size_var`/`font`/`weight_var` name the variable a click opens, `source` is `''`, `stylesheet` (a built-in the stylesheet changes) or `class`.
- Spacing: the base unit at 1, 2, 3, 4, 6, 8 units, the section rhythm, then own sizes. Shape: corners, line width, shadow (shadow by its option's label). Controls: main, second and outline button, link, text field, choice list, tick box, each with its states, rest first and off (`disabled`) last; `states` gives the state names.
- Components: `id` and `name`, sorted by name without case; a system widget (`system_region_config` set) and a row without id or name are left out. Drawing only the first screen at once is SG2's.

## How is the stylesheet read?
- Top-level rules only: comments and every at-rule block (`@media`, `@supports`, `@font-face`) are left out, a later rule wins.
- A lone selector `h1`-`h6`, `.h1`-`.h6`, `.display-1`-`6`, `.lead`, `body`/`p`, `small`/`.small` changes that built-in style; a lone class with a `font-size` is an own style named `.class`, at most `PG_STYLE_GUIDE_OWN_STYLES_MAX` (12). Compound selectors are not styles.
- Only plain values are taken: font-size `n`rem/em/px (a `calc()` keeps the default), font-family through the variables' font check, weight 100-900 or bold/normal, a bare line-height number.

## Which contrast pairs are measured?
- Light then dark: every colour but the surface as text on the surface (own colours included, marked `custom`), and the surface as a button label on brand and accent.
- Each pair: `ratio` (two places, `_pg_theme_contrast()`), `ok` at 4.5:1 (body text), `large_ok` at 3:1 (large text). Bootstrap's white on `#0d6efd` measures exactly 4.5.
