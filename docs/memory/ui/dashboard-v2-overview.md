---
name: dashboard-v2-overview
description: The new dashboard as a whole: the per-person switch beside the untouched old dashboard, welcome.php's page branch, its files and bundles, SVG charts, refresh rules, string card ids
---

## What is the new dashboard?
- A second dashboard on `welcome.php` from the owner's design (2026-11-01, #4692-#8350): greeting card with since-sign-in chips, shortcuts and four tiles, the System Status card, a grid of cards in three widths with their own period and compare, edit mode with presets, gallery, looks and role defaults. Each person turns it on or off in the switch row; off by default.
- Owner (2026-11-01): the old dashboard stays untouched (markup, script, `dashboard.order_widgets`, the Appearance card, widget ids, its actions) and only gains the switch row as the first row of `#content`. The design's header bar is not built (the panel's top bar has search, bell and the site link), so "Edit dashboard" and the saved chip sit in the switch row.
- Widget numbers stay permanent; the new cards have string ids (`ai`, `goals`, `visitmap`, …) in `pg_dashboard_v2_cards()` and never take a widget number.

## How does welcome.php reach the new page?
- After the access check and before any output, `welcome.php` loads `includes/dashboard/v2/layout.php`; when `pg_dashboard_v2_enabled()` is true it requires `includes/dashboard/v2/page.php` and exits. Otherwise the old page draws with `pg_dashboard_v2_switch_row(false)` above `.pg-clockbar`.
- `page.php` runs in `welcome.php`'s scope: the same `pg_page_shell()` title and heading, the switch row, greeting and status drawn on the server, the grid with each card's frame and skeleton, and one JSON config (`pg_dashboard_v2_config()`) for the script.
- Without the table `pg_dashboard_v2_row()` answers null (the query fails without throwing), so the old page draws.

## Which files?
- PHP in `includes/dashboard/v2/` behind `PG_DASHBOARD_V2` (defined in `layout.php`): `page.php`, `page_greeting.php`, `page_status.php`, `page_grid.php`; `layout.php` (row, switch, clean, save, role defaults); `cards.php` (list, gates, timing); `run.php` (loader); `presets.php`, `figures.php`, `gallery.php`, `personal.php`, `team.php`, `tips.php`; one `cards/<id>.php` per card.
- Script: bundle `assets/js/pg_dash.min.js` in `pg_js_bundles()`: `pg_dash.js` (grid, frame, loading, refresh, charts), `pg_dash_edit.js`, `pg_dash_panel.js`, `pg_dash_brief.js` and one `pg_dash_cards_<group>.js` per card group registering its renderers; a new group appends one line.
- Styles: `assets/css/pg_dash.src.css` in `pg_css_bundles()`. Bundle and stylesheet names take no digits (`tests/js_bundles_test.php`, `tests/css_minify_test.php`), hence `pg_dash`. Tests: `tests/dashboard_v2_<topic>_test.php`.

## How are charts drawn and when do cards refresh?
- Inline SVG drawn by `pg_dash.js` with `role="img"` and a label; Chart.js is not loaded on this page. A tone also differs in text, never colour alone. Tips: `dashboard-v2-chart-tips.md`.
- `pg_dashboard_v2_card_timing()`: `engagement`, `firewall`, `visitors` are asked for again at the session's refresh time (`pg_dashboard_refresh_seconds()`, at least 10 s); the rest on load and when a period or compare changes. A hidden tab skips it; coming back asks at once. The slow `gsearch`, `ga`, `flows` are asked for on their own beside the batch.

## Where is the rest?
- Table, layout JSON, actions, card contract: `dashboard-v2-storage.md`. Edit mode, share, side panel, cards, looks, width, phone: the other `dashboard-v2-*.md`. Owner decisions: `workflow/decisions-made-product-17.md`.
