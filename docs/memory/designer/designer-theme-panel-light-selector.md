---
name: designer-theme-panel-light-selector
description: Which selector the designer's Themes panel writes light-mode variables under, why it is :root:not([data-bs-theme="dark"]), and how old designs are rewritten.
---

## Where do the Themes panel's values go?
- Into the design's User Styles (`cssFiles` entry `meta: 'user-styles'`, published as `<style id="pg-user-styles">` after bootstrap.css). Light mode is one rule block, dark mode `[data-bs-theme="dark"] { … }`. Helpers in `assets/js/style_designer.js`: `_themeSelectorRe()`, `_themeCanonicalSelector()`, `_themeUpgradeLightSelector()`, `_writeThemeVar()`.

## Why is the light block `:root:not([data-bs-theme="dark"]), [data-bs-theme="light"]`?
- It used to be `:root, [data-bs-theme="light"]`. `:root` also matches an `<html data-bs-theme="dark">` a theme switcher sets, and at equal specificity the later user styles beat Bootstrap's dark block: with Background `#fdf6e3` set for light mode, "Dark" kept the light page and turned the text light grey.
- The new form covers the default page and an explicit light section, never a dark `<html>`. A dark section inside a light page still gets the dark values (its own declarations win over the inherited ones).
- The editor canvas hid the bug: its toolbar dark view puts `data-bs-theme` on `<body>`, where Bootstrap's dark block wins over the inherited `:root` value.

## Which light forms are read?
- The new form, the old combined form, a bare `:root` and a bare `[data-bs-theme="light"]` (quoted, either quote style). The `:not(...)` inside the new form is never taken for the dark block: the dark regex needs `{` right after `]`.

## How are designs saved with the old selector fixed?
- In the editor only, no server rewrite and no upgrade step: `_ensureBootstrapSentinels()` rewrites the old combined form when the design loads, and `_writeThemeVar()` does it before every write. The design then reads as changed and is fixed on its next save; a design nobody opens keeps the old block.
- A bare `:root` or `[data-bs-theme="light"]` block is hand-written and may hold the operator's own variables that must stay in dark mode, so it is left as written.
- "Save these colours as a theme" (`_sdThemeCssFromUserStyles()`) writes the canonical selectors; theme files saved before keep theirs.
- Palette files (`pg_design_palette_css()`) keep `:root, [data-bs-theme=light]`: they set every variable they override in their dark block too.

## What keeps this from coming back?
- `tests/designer_theme_light_selector_test.php` runs the helpers under Node (written selector, every read form, the rewrite and its call sites).
