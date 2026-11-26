---
name: image-editor-site-colors
description: The Artifact Canvas's "Site colours" row in every colour control (AC4): which colours, their order and names, the site_colors action, the colors part and the core's swatchRows hook
module: image_editor
---

## What does a person see?
- Every colour control (`X.swatches`) starts with a row labelled "Site colours" above the eight core swatches: at most twelve swatches, each titled with its name. A site whose designs set no palette and no variables shows no row.
- A press picks the colour as a core swatch does (`pick(color, false)`); the chosen one is marked `on`.

## Which colours, in which order?
- From every visual design (`style_type = 'system'`, newest `style_timestamp` first, 50 at most, Bootstrap designs only when the framework column exists).
- First each design's palette (`style.style_palette`): its primary and secondary, named "{palette}: primary colour" / "{palette}: secondary colour". No palette (`''`, plain Bootstrap) gives none.
- Then the colour variables of designs with a variables file (`pg_design_variables_stored()`, `designer/design-variables-tab.md`), light value: first those a designer set (a built-in one that differs from the design's default, every own colour), then the untouched ones (Bootstrap's success, warning, text...). Built-in ones are named by their label (Brand, Accent...), own ones by their name.
- Each colour once (`#abc` written out), anything but a hex colour dropped, cut at twelve: `pg_image_editor_site_colors_list()`.

## Where is the code?
- PHP under "Site colours" at the end of `includes/fn/image_editor.php`: `pg_image_editor_action_site_colors()` (`{ action: 'site_colors' }` → `{ status, colors: [{ color, name }] }`), pure helpers `pg_image_editor_site_palette_colors()`, `pg_image_editor_site_variable_colors()`, `pg_image_editor_site_colors_list()`; label `siteColors` in `pg_image_editor_strings_colors()`.
- `assets/js/pg_image_editor_colors.js` (part `colors`, bundled before `actions` and `menu`: the menu test wants the menu last). It asks once a page, as the first editor opens (`Editor.inits`); rows drawn before the answer wait hidden and fill when it comes, or are removed when it is empty. No `api` in the config: no row.
- The model checks the answer: `M.siteColors()` (section `SITECOLORS`). Tests: `tests/image_editor_colors_test.php`.

## Why no schema change?
- Palettes and variables are already stored (`style.style_palette`, the design's `design-variables-<id>.css`); the action only reads them (PR #NNNN).
