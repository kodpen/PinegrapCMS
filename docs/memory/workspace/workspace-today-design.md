---
name: workspace-today-design
description: Why the Workspace Today page is drawn as new-dashboard cards: fixed cards, the look it borrows, where styles and methods live, no .ws-home-* rule left
---

## What was done, and why?
- Today (`workspace.php?view=home`) was redrawn as new-dashboard cards so it reads like the panel's dashboard (owner 2026-10-31, `workflow/decisions-made-workspace-5.md`). Address, data route (`ws_home()`) and drawing place (`showHome()`, `drawHome()`) stayed; only markup and styles changed, every link and action kept.
- Shell, greeting and the shared card frame `todayCard()` came first (#6718); the cards followed: Waiting and Due (#6745), Calendar (#6739), Digest and Decisions (#6735), Join and About (#6743). The close-out removed the old Overview rules.

## What is fixed on purpose?
- No edit mode, drag, period switch or layout store: the cards stand in the fixed order of `workspace-today-page.md`, with fixed widths (S, M, L) and categories (team, general).
- pg_dash.min.js is not loaded: the cards are drawn with workspace.js's `el()`, one method per card (`homeWaiting` … `homeAbout`).
- The card face follows the person's dashboard look (`pg_dashboard_v2_look()` style and calm, via `ws_today_look_for()`); the dashboard's backdrop and dense switch do not apply, the Workspace keeps its own density button.

## Where are the styles?
- `assets/css/ws_today.src.css` (bundle in `pg_css_bundles()`, built by `tools/build_css.php`), loaded after pg_dash.min.css (`ws_today_stylesheets()`): page, card frame, then one section per card. It adds to the dashboard's `.pg-dash-*` classes and `--pgd-*` palette, every rule under `.ws-today`.
- backend.src.css keeps only `.ws-home` (the scroll area) and `.ws-home > *`; every `.ws-home-*` rule is gone. The one old class still on a card is `ws-home-about` (What is the Workspace?), styled in ws_today.src.css. `tests/workspace_today_plan_test.php` fails if a `.ws-home-` rule comes back.
- A card's rows use `ws-today-*` classes; the tests of each card check that no `ws-home-` class is drawn (`tests/workspace_today_test.php`, `tests/workspace_today_digest_test.php`).

## How narrow does it go?
- At 360 px the grid is one column, the greeting's buttons wrap under the title, the About tiles stack (`minmax(min(100%, 13rem), 1fr)`); nothing scrolls sideways.
