---
name: designer-theme-panel-light-selector
description: Which blocks the designer's Themes panel writes light and dark variables into, why colours and fonts/radii use different light selectors, and how old designs are split.
---

## Where do the Themes panel's values go?
- Into the design's User Styles (`cssFiles` entry `meta: 'user-styles'`, published as `<style id="pg-user-styles">` after bootstrap.css), in three blocks (`_themeBlockFor()`):
  - light: `:root:not([data-bs-theme="dark"]), [data-bs-theme="light"]` for a light value of a variable Bootstrap's `[data-bs-theme=dark]` block gives a dark value (`_themeDarkOwnVars()`: body/secondary/tertiary colours, emphasis, subtle colours, heading, link colours, code, highlight, border colour, form states);
  - shared: `:root, [data-bs-theme="light"]` for a light value of any other variable (font, size, weight, line height, link decoration, border width and radii, focus ring, the eight theme colours, custom variables);
  - dark: `[data-bs-theme="dark"]` for every dark value.
- Helpers in `assets/js/style_designer.js`: `_themeFindBlock()`, `_themeCanonicalSelector()`, `_themeSetDecl()`, `_themeUpgradeLightSelector()`, `_themeModeDecls()`, `_writeThemeVar()`.

## Why two light selectors?
- `:root` also matches an `<html data-bs-theme="dark">` a theme switcher sets, and at equal specificity the later user styles beat Bootstrap's dark block: a light Background `#fdf6e3` kept a "Dark" page light with light grey text (fixed in #1298). So colours Bootstrap redefines for dark use the `:not()` form.
- Bootstrap has one value for both modes for the rest, so a dark `<html>` with the `:not()` form fell back to Bootstrap's defaults (font system-ui, radius 6px). Those stay under `:root`.
- The shared block is created before the dark block, so a Dark value of the same variable wins on a dark `<html>`. The light block wins over the shared one (`(0,2,0)`); a hand-written `:root` rule in a stylesheet ordered after User Styles loses to it.
- The editor canvas hid the bug: its toolbar dark view puts `data-bs-theme` on `<body>`.
- The helpers hold no setup-scope `var`: the load-time split runs before the setup reaches them (a `var` list was still undefined and broke the editor).
- In Dark mode a shared variable's placeholder is the light value it inherits.

## Which light forms are read?
- The light form, the old combined form, a bare `:root` and a bare `[data-bs-theme="light"]`, either quote style, each from its first selector; the tail of a combined form is never taken for a bare block. In Light mode the panel shows the light block over the shared one. A panel write puts the value in its own block and removes it from the other light block.

## How are designs saved with the old selector fixed?
- In the editor: `_ensureBootstrapSentinels()` on load, `_writeThemeVar()` before every write. Colours move to the light block (the block is renamed when it held only colours; an existing light block takes the moved ones it lacks); the rest stay, moved before the dark block when after it (found again after the cut).
- On the server: step 11.0.31 makes the same split for designs nobody opens, old page copies, design templates and old theme files (`designer-theme-light-selector-upgrade.md`).
- A bare `:root` or `[data-bs-theme="light"]` block is hand-written and is left as written.
- "Save these colours as a theme" (`_sdThemeCssFromUserStyles()`) writes the shared, light and dark blocks with these selectors.
- Palette files (`pg_design_palette_css()`) keep `:root, [data-bs-theme=light]`: they set every variable they override in their dark block too.

## What keeps this from coming back?
- `tests/designer_theme_light_selector_test.php` runs the helpers under Node: the selectors, `_themeDarkOwnVars()` against the shipped bootstrap 5.3.8 dark block, every read form with its offset, the split, panel writes, a small cascade check (dark page keeps font and radius, gets the dark background) and the call sites with comments stripped; `tests/designer_theme_vars_test.php` checks the PHP twin.
