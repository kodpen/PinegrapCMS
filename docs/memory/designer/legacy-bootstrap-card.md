---
name: legacy-bootstrap-card
description: The "Towards Bootstrap 5" card (LB3): Legacy designs, Bootstrap 3 found by the framework sheet row, a design's report (names, sheets, scripts, files left out), nothing written
module: legacy_bootstrap_report
---

## Where is it, and who sees it?
- `legacy_to_designer.php`, the card "Towards Bootstrap 5" (`#ltd-bootstrap`) after Back to classic; designers (role 0-1) as the whole screen. Module `includes/fn/legacy_bootstrap_report.php`; the converter it runs is `legacy-bootstrap-map.md`.
- One line per Legacy design (`style_framework = 'legacy'`, by name): its pages (binned left out) and its framework sheet. Report is a GET link `?bootstrap=<id>#ltd-bootstrap-<id>`; "Hide the report" goes back. Nothing is written, so no token and no refusal while a move runs.

## How is Bootstrap 3 found?
- `pg_lbs_design_assets()` (pure apart from a banner reader): an enabled linked stylesheet row, not an editor `meta` row, named `bootstrap.css` or `bootstrap.min.css`; never by class names (owner, #8967; Materialize shares six of them, `legacy-materialize-trial.md`).
- A version in its address (`bootstrap/3.3.7/`, `bootstrap@5.3.8`) or else in the site file's banner (`Bootstrap v3.3.5`, 5.x writes two spaces) other than 3 is another Bootstrap; no version reads as 3.
- Not Bootstrap 3: "This design does not use Bootstrap 3 …" (or "Its bootstrap.min.css is Bootstrap 4 …"), no Report button. LB4's trial design must keep the same test.

## What does a report hold?
- `pg_lbs_design_report($id)`: the pages' trees (`pg_page_tree_sql_expr()`) and every shared part they place, parts inside parts too, through `pg_lbs_trees_report()`: converted, by hand and kept names with their uses, by hand and kept with where (page name, or "name (shared part)").
- Stylesheets: the design's kept CSS rows and its Theme file (its `theme_id`, else the activated theme `pg_legacy_design_classic_theme()` gives) through `pg_lbs_css()`. A site file is read (at most 4 MB); a CDN or `{software_directory}` file is listed "Not read". Inline CSS rows are read too.
- Scripts: the kept site JS files and inline rows searched by `pg_lbs_script_calls()`: Bootstrap 3 jQuery plugin calls (`$(…).tooltip(`) and data names 5.3.8 renames; `//` lines and block comments skipped.
- Files left out (`left_out`): the framework sheet rows and Bootstrap 3's script (`bootstrap.js`, `bootstrap.min.js`). The script is the worker's reading of the LB1 trial, which swapped both; LB4 decides what its asset rows drop.

## What did the sandbox show (turkish_default, four sets moved)?
- emailer-inline seti: no Bootstrap sheet, the line and no button. auto-dialog, home-1 and home-2 seti: Bootstrap 3 (bootstrap.css).
- home-2 seti in under a second: 146 pages, 194 parts, 588 uses of 65 names converted, by hand `col-md-push-6`, `col-sm-push-8`, `col-sm-pull-4` (two image-edge parts), nothing kept; `theme_modern.css` 160 names renamed, themify, lightbox, ytplayer and the inline row none; flexslider (CDN) and livesite.src.css not read; scripts.js `tooltip()` 1, `data-toggle` 1.

## Tests?
- `tests/legacy_bootstrap_report_test.php`: names and versions, Bootstrap 3 found and not (Materialize, 4, 5, disabled, meta, inline), the starting sites' heads, site files, script calls (the starting scripts.js), trees and sheet reports, the card's markup and escaping, the screen's wiring, no write in the module.
