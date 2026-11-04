---
name: visitors-screen
description: The Visitors overview screen (visitors.php): the period and its query string, the period bar, the section loader and contract, the shell helpers, its CSS/JS bundles, the Visitors menu and panel search
module: visitor_periods
---

## What is the screen?
- `visitors.php` (root): `validate_user()`, `validate_visitors_access()`, the period from `$_GET`, the toolbar (Saved reports; the dates and the compared dates in the grow slot; the period bar in the search slot), then `pg_visitors_render_sections()`, every section file present (alerts to places, #6361-#6516). Opened by the Visitors menu entry. What a section may read and edit: `ui/visitors-section-rules.md`.
- Its stylesheet `assets/css/pg_visitors.src.css` (`pg_css_bundles()`, linked through `pg_visitors_style_url()` in the head) and script `assets/js/pg_visitors.src.js` (`pg_js_bundles()`); Chart.js is loaded only when a section printed a `data-pg-chart` canvas.

## How is the period read?
- `pg_visitor_period($request, $now)` (`includes/fn/visitor_periods.php`, pure): `p` in today, yesterday, 7d, 30d, 90d, month, last_month, custom (`from`, `to` Y-m-d; reversed dates swapped, a future day becomes today, one end a one-day range); anything else is 7d. `compare` is on unless `'0'`.
- Answers `from`/`to`, `from_ts`/`to_ts` (to excluded; today ends now), `days`, `grain` (hour 1 day, day ≤31, week ≤120, month), `previous_*`: the same days just before; this month against the same days of last month (cut to its end); last month against the month before.
- Ending today, the earlier period is cut at the same wall-clock time of its last day (widget 5's rule), DST-safe. Days are stepped from noon.
- `pg_visitor_period_bucket()` / `_buckets()`: chart buckets, weeks start on Monday. `pg_visitor_period_query($period, $change)`: the query string a link keeps (`p`, the range for custom, `compare=0`). The period is never kept in the session.
- The calendar fields post the site's date order; `visitors.php` turns them into Y-m-d with `prepare_form_data_for_input()` before the pure function.

## How does a section land?
- `pg_visitors_sections()` (same module): name => heading, in screen order: alerts, traffic, conversions, sources, content, site_search, google, pages, speed, ai, places. The file `includes/visitors/<name>.php` (gate `PG_VISITORS_SCREEN`) defines `pg_visitors_section_<name>($period, $user)` returning HTML or `''`. The loader includes it when `pg_visitors_section_exists()`; the screen is not edited.
- Draw with `includes/visitors/shell.php`: `pg_visitors_section()` (anchor `pg_visitors_anchor()`), `pg_visitors_block()`, `pg_visitors_tile()`, `pg_visitors_change()` (none or 0 before: a dash read out as "No earlier figure", never 0 %), `pg_visitors_change_cell()`, `pg_visitors_bars()`, `pg_visitors_table()` (text escaped, `array('html'=>…)` raw, `limit`, `hide_first`), `pg_visitors_empty()`, `pg_visitors_chart()` (canvas `aria-hidden`, JSON beside it, the figures in a hidden box). The look: `visitors-screen-look.md`.
- Each section edits only its labelled block in `pg_visitors.src.css` and runs `php tools/build_css.php`.

## What do the menu and search show?
- Menu item 6 opens `visitors.php`: Overview, then each section whose file exists as `visitors.php#<anchor>` (`.pg-menu-anchor`); Site Search, Page Speed and AI Visibility only when `pg_site_search_stats_ready()`, `pg_vitals_ready()`, `pg_ai_visibility_ready()` (their old screens answer a 301 to the anchor); then Saved reports, Create a visitor report, the order and shipping block, Search Console settings, external analytics. `visitors.php` is in the active-menu switch.
- Panel search: "Visitors" → `visitors.php`. Help: `get_help_url.php` `visitors` → `visitors#overview`.

## What pins it?
- `tests/visitor_periods_test.php` (presets, month lengths, same-hour cut, DST, custom, bad input, Monday weeks, query) and `tests/visitors_shell_test.php` (change, escaping, chart data, section list).
