---
name: visitors-section-rules
description: Rules for a Visitors overview section (includes/visitors/<name>.php): what it owns, where its figures come from, texts and formats, access, files never touched
---

## What does a section own?
- Its `includes/visitors/<name>.php`, its labelled block in `assets/css/pg_visitors.src.css` (then `php tools/build_css.php`), `tests/visitors_<name>_test.php` and its topic (`visitors/visitors-<name>.md` or `ui/visitors-<name>-section.md`). Contract and loader: `visitors-screen.md`.
- A change to one section never edits `visitors.php`, `shell.php`, `pg_visitors.src.js`, the figures module or another section. A helper only it needs stays in its file (or its own `includes/fn/` module, as `visitor_pages`); one a second section needs moves into `shell.php` or `visitor_figures.php`.
- A new section: a name and heading in `pg_visitors_sections()` (screen order) and the file; the menu anchor follows by itself.

## Where does a figure come from?
- Visits, page views, forms, orders and every dimension: `includes/fn/visitor_figures.php` (`visitors/visitor-figures-module.md`): the daily summary for whole past days it covers, the raw rows for today and the rest. Never `COUNT(*)` over `visitors` in a section.
- Traffic by hour or day: `visitor_stats_hourly` (`pg_visitor_traffic()`); content: `visitor_content_hourly` labelled by `pg_visitor_label_content_rows()`.
- People on the site now: `visitors.stop_timestamp` in the last 20 minutes (the dashboard's rule).
- Google: `pg_gsc_*()` over `seo_search_daily`; its period ends on `pg_gsc_latest_day()`, said on the screen (`visitors/visitors-google.md`). Pages meet Google rows on `pg_visitor_page_key()` (`visitors/visitors-pages.md`).
- Alerts are computed at render time from the same figures: no table, no job (owner decision 7).
- A new section's figure is checked once against the saved-report screen for the same window: the two agree to the visit.

## How is it printed?
- Texts through `lang()`, added with `php tools/tr_add.php`; every value through `h()` (`pg_visitors_table()` escapes text cells); counts `pg_format_number()`, percents `pg_visitors_percent()` (the `'{var:1}%'` key, Turkish `%12`, a sign or arrow outside; never a `%` glued in code, `tests/visitors_shell_test.php` reads the files), money from minor units through `prepare_amount()` / `pg_money_text()`; dates in site time.
- Markup in Bootstrap 6 (`php tools/check_bs6.php --strict`), wide tables in `.table-responsive`; CSS in `@layer custom` on `--pg-*` tokens so light and dark both hold (`panel-css-tokens.md`). Charts through `pg_visitors_chart()`: Chart.js with animation off, a hidden table of the figures for screen readers; a series' `unit` follows its figures, `notes` add tooltip lines (split on `\n`).
- No data: `pg_visitors_empty()` with one line saying why, or `''` when a section has nothing to say (alerts with nothing fallen; speed, ai and site search before their ready checks).
- Links keep the period with `pg_visitor_period_query()`; a section's own filter takes a prefixed name (`speed_device`) so another section's query value is never read.

## Who sees what?
- `validate_visitors_access()`: role under 3 or `manage_visitors`. Order, revenue and conversion figures only with `ECOMMERCE`; with commerce off a section shows its other figures.
- `manage_ecommerce_reports` alone never reaches the screen: the order and shipping reports stay in the Visitors flyout and are reached from the Ecommerce menu (owner decision 4).
- The screen never changes `welcome.php`, `includes/dashboard/` or `search_console.php`; an unlinked Search Console is a small card linking to `search_console.php` (decisions 5 and 6, `workflow/decisions-made-product-24.md`).
