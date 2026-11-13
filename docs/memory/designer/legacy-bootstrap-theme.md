---
name: legacy-bootstrap-theme
description: A Legacy design's Theme file read as a palette and a look its Bootstrap 5 trial design wears (LB7, #9328): what is read, the imported colour sheet, the files made, the card
module: legacy_bootstrap_theme
---

## Where is it?
- Under a trial design on the "Towards Bootstrap 5" card (`#ltd-theme-<legacy id>`): "Make a palette and a look from the Theme file" (POST `bootstrap_theme`, token, designers). Once made: the two colours, the fonts, and whether the trial still wears them. Module `includes/fn/legacy_bootstrap_theme.php`.
- Owner (2026-11-12, #8942): the theme becomes a palette and a look, after the pages are moved (`workflow/decisions-made-product-46.md`).

## What is read, from where?
- `pg_lbs_theme_sheets()`: the trial's Theme file (its copy), else the Legacy design's (theme_id, else the activated theme), then the trial's site sheets and inline sheets. A sheet's `@import 'X.css'` site files come right before it (`pg_lbs_theme_imports()`, comments skipped).
- Why the imports: the starting sites' `theme_modern.css` chooses its colour sheet with `@import 'FoundryGreen.css';`; its `!important` rules are the green a visitor sees, while the theme file itself names red #e31d3b.
- `pg_lbs_theme_read()` (pure), first sheet that names one wins: primary = background of `.bg-primary` or `.btn-primary`, else colour of `.color-primary` or `a` (a grey passed over), else the most used non-grey colour; secondary = background of `.bg-dark`, else body text colour if dark, else #292929; body and heading fonts = font-family of `body` and `h1` (icon fonts and odd characters refused). Colour names (Blue.css) and rgb() are read.

## What is made?
- A palette (`pg_design_palette_css()` with its pg-theme header, as the editor saves one) and a look (the default look's tokens with `--pg-font-body` and `--pg-font-heading`), both through `pg_design_theme_save_file()` named after the Legacy design; the trial's `style_palette` and `style_look` set to their `file-<id>`. Recorded in `trials.json` under `theme`.
- They stay in the Themes tab for every design and are not deleted with the trial.
- The theme's own sheets still win on the page; the palette gives Bootstrap's own parts (`--bs-primary`) the theme's colour. Then the Theme file can be taken off in the Page Design Studio, Settings (the existing Theme file select).

## Sandbox (turkish_default, home-1 set)?
- Read #47b475 (FoundryGreen), #666666, "Open Sans" body, "Raleway" headings; the trial's pages load bridge, base, the look and the palette; buttons and links stay green.

Tests: `tests/legacy_bootstrap_theme_test.php`.
