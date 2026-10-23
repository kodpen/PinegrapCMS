---
name: design-theme-look-and-palette
description: A visual design's look and colour palette (style.style_look / style_palette, 2026.4.5): values, the stylesheets and their load order on site, canvas and preview, tokens for new component styles.
---

## What does a design wear?
- Two independent stylesheets (`includes/fn/design_themes.php`): the look (`style.style_look`; `assets/css/themes/looks/<key>.css`, only `--pg-*` tokens: corners, shadows, lines, type) and the colour palette (`style.style_palette`; `assets/css/themes/palettes/<key>.css`). Columns from 2026.4.5 (`pg_design_look_ready()`).
- A value is `''` (plain Bootstrap), a built-in key, or `file-<id>`: a look or palette made in the editor, saved as a design CSS file in the file manager that starts with `/*! pg-theme {json} */`, so other designs can wear it. Nothing is written into the pages.
- Only Bootstrap designs wear them: a design without a framework gets none (`pg_design_theme_assets()`).
- The theme file (`style.theme_id`, a `files` row with `design = 1`, "Save as theme" → `designer/theme_save`) is a separate stylesheet, chosen in the same Theme pane.

## In what order do the stylesheets load?
- bootstrap.css → `bridge.css` (Bootstrap's literal component colours pointed at variables; loaded when a look or a palette is set) → `base.css` (the look tokens applied to every component; with a look) → the look → the palette. The design's own stylesheets, User Styles and the theme file still win.
- Every surface puts them right after the last Bootstrap link: the site through `get_page_content.php` (`pg_design_theme_links()`), the canvas through `_sdThemeApply()` (also run at the end of `applyAssetsToIframe()`, which re-adds the links), the preview through `_sdPreviewDocHtml()`.

## How does a new component style use the theme?
- No fixed colours: `var(--bs-primary)`, `--pg-on-primary` (text on the colour), `--pg-primary-fg` (the colour as text on the page); shape through `--pg-radius-*`, `--pg-shadow*`, `--pg-border-width`.
- A token is declared on `:root, [data-bs-theme]`, so a colour-based token is worked out again inside a section that switches to dark.
- Starter trees use Bootstrap classes and utilities only: the theme dresses them. A widget container that prints pagination carries `py-4`.

## How are palette files made?
- Never by hand: `php tools/build_theme_palettes.php` writes `palettes/<key>.css` from `pg_design_palettes()` with the generator a custom palette uses (`pg_design_palette_css()`); `--check` reports stale files.

Related: docs/memory/designer/design-framework.md
