---
name: visitors-overview-design
description: Contract of plans/visitors-overview.md: the files and who owns each, the period model, the section function, the menu, access and texts; the data side is in visitors-overview-data.md
---

## Which files, and who owns them?
- `visitors.php` (root, V1): `validate_user()`, `validate_visitors_access()`, the period from the query string, `pg_page_shell()` with the period bar in the toolbar (`ui/admin-screen-toolbar.md`), then the sections in this order: alerts, traffic, conversions, sources, content, site_search, google, pages, speed, ai, places. The loader includes `includes/visitors/<name>.php` when it exists and calls `pg_visitors_section_<name>($period, $user)`, so a section lands on its own and the page is never edited for one.
- `includes/visitors/<name>.php`, gate `PG_VISITORS_SCREEN`: one function returning HTML (`''` draws nothing), tests in `tests/visitors_<name>_test.php`, topic `visitors/visitors-<name>.md`. One step each; nobody else edits them.
- `includes/visitors/shell.php` (V1): `pg_visitors_tile()`, `pg_visitors_change($now, $before, $lower_is_better)` (arrow, percent, "no earlier figure"), `pg_visitors_bars()`, `pg_visitors_table()`, `pg_visitors_empty()`, `pg_visitors_section($id, $title, $body, $aside)`, `pg_visitors_chart($id, $config)`: a canvas and a JSON script beside it; `assets/js/pg_visitors.src.js` draws every `[data-pg-chart]` with `assets/lib/chartjs`, colours from `--pg-*` tokens, redrawn on a theme change. A section needing one more helper adds it as a small edit named in its PR body.
- `assets/css/pg_visitors.src.css` and `.min.css` (`pg_css_bundles()`), `assets/js/pg_visitors.src.js` and `.min.js` (`pg_js_bundles()`): V1 writes the stylesheet with one labelled, empty block per section, so each step edits only its block and two steps in one train merge clean.
- `includes/fn/visitor_periods.php` (V1): the period. `includes/fn/visitor_figures.php` (V2): the figures (`-data.md`), also read by the API. Both named in the `module:` field of their topics.
- Not touched: `welcome.php`, `includes/dashboard/`, `search_console.php` (decisions 5 and 6).

## What is the period?
- `pg_visitor_period($request)`: `p` in today, yesterday, 7d, 30d, 90d, month, last_month, custom (`from`, `to` as Y-m-d); `compare` (1 unless 0). Answers `from`/`to` by the site's clock, whole days (today ends now), `previous_from`/`previous_to` of the same length before it (last month against the month before), `grain` (hour for one day, day up to 31, week up to 120, month beyond; weeks start Monday), `days`, `label`, `key`. Bad input falls back to 7d. Pure. The keys match `pg_visitor_insight_window()` (`includes/fn/visitor_insights.php`), which the speed and ai sections take.
- Today against yesterday is cut to the same hour, as widget 5 does; no previous figure reads "no earlier figure", not 0 %.
- Links keep `p`, `from`, `to`, `compare`; a section is an anchor (`#sources`). The period is never kept in the session.

## Which menu, words and access?
- `includes/fn/output.php`, menu item 6: `visitors.php` as entry and in the active-menu switch; flyout: Overview, then the sections as anchors (Page Speed, AI Visibility and Site Search take the place of their screens' entries, under the same ready checks), a divider, Saved reports and Create a visitor report, the order and shipping block as it is, a divider, Search Console settings, the external analytics link. Panel search and `get_help_url.php` learn the screen.
- Texts through `php tools/tr_add.php`; values through `h()`; numbers `pg_format_number()`, money `prepare_amount()`; dates in site time.
- `validate_visitors_access()`; e-commerce figures only with `ECOMMERCE`. `manage_ecommerce_reports` alone never reaches the screen (decision 4).
