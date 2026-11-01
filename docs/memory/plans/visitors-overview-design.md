---
name: visitors-overview-design
description: Contract of plans/visitors-overview.md: the files and who owns each, the period model, the section function, the menu, access and texts; the data side is in visitors-overview-data.md
---

## Which files, and who owns them?
- `visitors.php` (root): `validate_user()`, `validate_visitors_access()`, the period from the query string, `pg_page_shell()` with the period bar in the toolbar (`ui/admin-screen-toolbar.md`), then the sections in this order: alerts, traffic, conversions, sources, content, google, pages, places. A section is `includes/visitors/<name>.php`; the loader includes the file when it exists and calls `pg_visitors_section_<name>($period, $user)`, so a section lands on its own and the page is never edited for one. V1.
- `includes/visitors/<name>.php`, gate `PG_VISITORS_SCREEN` (defined by `visitors.php`, and by the API where it reuses one): one function returning HTML (`''` draws nothing), tests in `tests/visitors_<name>_test.php`, topic `visitors/visitors-<name>.md`. One step each; nobody else edits them.
- `includes/visitors/shell.php` (V1): `pg_visitors_tile($label, $value, $change, $hint)`, `pg_visitors_change($now, $before, $lower_is_better)` (arrow, percent, "no earlier figure"), `pg_visitors_bars($rows)`, `pg_visitors_table($columns, $rows)`, `pg_visitors_empty($icon, $text)`, `pg_visitors_section($id, $title, $body, $aside)`, `pg_visitors_chart($id, $config)`: a canvas and a JSON script beside it; `assets/js/pg_visitors.src.js` draws every `[data-pg-chart]` with `assets/lib/chartjs` (line, bar, stacked bar, sparkline), colours from `--pg-*` tokens, redrawn on a theme change. A section needing one more helper adds it as a small edit named in its PR body.
- `assets/css/pg_visitors.src.css` and `.min.css` (`pg_css_bundles()`), `assets/js/pg_visitors.src.js` and `.min.js` (`pg_js_bundles()`): V1 writes the stylesheet with one labelled, empty block per section (`/* traffic */` ...), so each step edits inside its own block and two steps in one train merge clean (an unchanged line between them).
- `includes/fn/visitor_periods.php` (V1): the period. `includes/fn/visitor_figures.php` (V2): the figures (`-data.md`); the API resource calls it. Both named in the `module:` field of their topics.
- Not touched: `welcome.php`, `includes/dashboard/`, `search_console.php` (decisions 5 and 6).

## What is the period?
- `pg_visitor_period($request)`: `p` in today, yesterday, 7d, 30d, month, last_month, custom (`from`, `to` as Y-m-d); `compare` (1 unless 0). Answers `from` and `to` as Unix times by the site's clock, whole days (today ends now), `previous_from`/`previous_to` of the same length ending the day before `from` (last month against the month before it, not 30 days), `grain` (hour for one day, day up to 31 days, week up to 120, month beyond; weeks start Monday), `days`, `label`, `key` for links. A bad or missing value falls back to 7d. Pure, no database.
- Today against yesterday is cut to the same hour, as widget 5 does. A section says "no earlier figure" rather than 0 % when the previous period has none.
- Links keep `p`, `from`, `to` and `compare`; a section is an anchor (`#sources`); the flyout's section items are those anchors. The period is never kept in the session.

## Which menu, words and access?
- `includes/fn/output.php`, menu item 6: `visitors.php` as its entry and in the active-menu switch; the flyout: Overview, Traffic, Sources, Content, Google search, Pages, Places (anchors), a divider, Saved reports and Create a visitor report, the order and shipping block as it is, a divider, Search Console settings, the external analytics link. `includes/panel/search.php` and `get_help_url.php` learn the screen.
- Texts through `php tools/tr_add.php`; every printed value through `h()`; numbers `pg_format_number()`, money `prepare_amount()`; dates in site time (`visitors/visitor-report-site-time.md`).
- `validate_visitors_access()` on the screen; e-commerce figures only when `ECOMMERCE` is on. `manage_ecommerce_reports` alone never reaches the screen (decision 4).
