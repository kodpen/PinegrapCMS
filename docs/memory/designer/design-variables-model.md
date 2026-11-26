---
name: design-variables-model
description: A design's variables (DV1, 2026.11.0): the list, defaults from look and palette, the variables stylesheet with its pg-theme head, the reader, the text-on-surface contrast check
module: design_variables
---

## Where is the model?
- `includes/fn/design_variables.php`, pure (no database, no request); tests in `tests/design_variables_test.php`. The tab, the file and its loading: `design-variables-tab.md`.
- A model is an array: `name`, `look`, `palette` (the choice the defaults came from), `values` (key => value, a colour is `array('light','dark')`), `custom` (the designer's own colours and sizes).

## Which variables are there?
- `pg_design_variable_list()`, grouped by `pg_design_variable_groups()`: Colour (brand, accent, neutral, surface, text, success, warning, danger), Type (font_heading, font_body, size_base, scale, weight_heading, weight_body, line_height), Spacing (space_unit, space_section), Shape (radius, border_width, shadow).
- Kinds: color, font (names, quotes, commas only), length (rem or px within the row's bounds, or a bare `0` where the bounds start at 0), font names each bare or in one closed pair of quotes, number, weight (100 to 900), choice. Shadow's `''` means "As the look"; the other options are the look builder's presets (`_pg_design_look_shadows()`, shared with the custom look).
- Custom variables: at most 24, kind `color` or `size`, key made a slug of up to 30 characters and kept once, name up to 40 characters.

## Where do the defaults come from?
- `pg_design_variables_defaults($look, $palette)`: brand and accent from `pg_design_palette_colors()`, the rest of the colours Bootstrap's (surface `#ffffff` / `#212529`, text `#212529` / `#dee2e6`, neutral `#6c757d` / `#adb5bd`); type and shape from the look's tokens (`pg_design_look_tokens()`), Bootstrap's where the look has none or a value fails the checks. A `file-<id>` look reads as none.

## What does the stylesheet hold?
- `pg_design_variables_css($model, $defaults)`: the head `/*! pg-theme {"kind":"variables","version":1,...} */`, then three rules: `:root, [data-bs-theme]` (type, spacing, shape), `:root, [data-bs-theme=light]` and `[data-bs-theme=dark]` (colours).
- Every variable writes its own `--pg-var-<key>` token (custom ones `--pg-var-c-<key>`). Only a value that differs from `$defaults` also writes the theme tokens it drives: colour roles through `_pg_theme_role()` (brand = primary, accent = secondary), surface `--bs-body-bg`, text `--bs-body-color`, neutral `--bs-secondary-color`, fonts, size, weights, line height, line width, the radius ladder, the shadow preset. So a file of untouched defaults changes nothing a page reads.
- A changed brand or accent also resets the role's `-line` tokens and the primary's check marks, or a pale palette's would outlive it.
- Scale, base unit and section rhythm drive no theme token yet: only `--pg-var-*` (read by bound nodes from DV3).
- The head stays under `PG_DESIGN_VARIABLES_HEAD_MAX` (8192 bytes); `pg_design_theme_read_header()` reads only 4096 and knows only `look` and `palette`, so a variables file is read by `pg_design_variables_stored()` (`design_themes.php`) over its first 8256 bytes.

## How is a file read back?
- `pg_design_variables_read($css, $defaults)`: the head's JSON with `kind: variables`, normalized against `$defaults`; null for any other file. Writing what was read gives the same bytes.
- `pg_design_variables_normalize($model, $defaults)` is the only gate: a refused value takes its default, unknown keys go.

## What does the contrast check give?
- `pg_design_variables_contrast($model)`: rows `text`, `neutral` on `surface`, light then dark, each with `ratio` (two places) and `ok` at 4.5:1 or more.
