---
name: visitors-overview
description: Plan (owner 2026-11-01): the Visitors section as one overview screen on the rollups and a daily summary written every day, Search Console joined to the pages; steps V1-V16 in the -steps files
---

## What is wrong now?
- The Visitors menu opens `view_visitor_reports.php`: a list of saved report names with no figure on it. "How is the site doing" has no screen; the nearest answer is dashboard widget 5.
- `view_visitor_report.php` is the 2001 report builder: three summarize-by levels and a filter table, saved and run for every question; a year / month / week / day stepper with the range in the session; a 14-column table, nine of them order percentages, no chart; weeks start on Sunday; `$` columns with commerce off.
- The figures exist as rollups (`visitor_stats_hourly`, `visitor_content_hourly`, `visitor_stats_daily`, `seo_search_daily`) and API routes, but the screens read the raw `visitors` table, and the daily summary is written only for days the retention is about to remove: every breakdown of the last 90 days scans the raw rows.
- Search Console has its own screen and period; nothing sets a Google page against the visits and orders it brought, although both are in one database.

## What is proposed?
- One screen, `visitors.php`: a period bar (today, yesterday, 7 days, 30 days, this month, last month, custom; compared with the previous period by default) and sections: alerts, traffic, conversions, sources, content, Google search, pages joined with Google, places and devices. The Visitors menu entry opens it; the flyout lists its sections, then the saved reports and the Search Console settings.
- The daily summary is written for every day through yesterday whatever the retention; the screen reads yesterday and before from it, today from the raw rows, the traffic line from the hourly rollup. One module (`includes/fn/visitor_figures.php`) answers the screen and the API, later the dashboard cards.
- The channel rule completed (paid, e-mail, AI assistants, organic, social, referral, direct); the search-engine list brought up to date.
- Search Console joined to the pages by address: impressions, clicks, CTR, position, visits, page views, forms, orders and SEO score per page; query opportunities.
- The report builder stays as "Saved reports" with presets instead of the stepper; the single-visit screen is kept.

## Which files hold the rest?
- `visitors-overview-design.md`: files and ownership, the period, the section contract, menu, access. `visitors-overview-data.md`: which table answers what, the day split, the channel rule, the page join.
- `visitors-overview-steps.md` (V1-V4), `-steps-2.md` (V5-V11), `-steps-3.md` (V12-V16). `visitors-overview-workers.md`: how two coordinators' workers take them.

## Which questions are decided? (owner 2026-11-01; `workflow/decisions-made-product-18.md`, the ENUM in `decisions-made-schema-8.md`)
1. One page with sections and deep links, not a screen per section.
2. The daily summary runs every day, independent of the retention period; the summary switch stays, retention alone decides what is removed.
3. The channel rule is completed and the search-engine list brought up to date; the ENUM is widened by appending.
4. The order and shipping report links stay in the Visitors flyout; a person with the e-commerce reports right alone reaches them from the Ecommerce menu and sees no visitor figure.
5. The dashboards are not touched; whether the new dashboard's cards reuse the sections is decided when its design is finished.
6. Google section not linked: a small card linking to `search_console.php`, neither an empty section nor a hidden one.
7. Alerts are in the first phase, computed at render time from the same figures; no table, no job.

## Where does it start?
- V1, V2 and V3 touch different files and start together; the sections after V1 and V2. Read `visitors/README.md`, `visitors/visitor-rollup-tables.md`, `visitors/visitor-retention-and-cleanup.md`, `ui/admin-screen-toolbar.md`; for V9 and V10 the header of `includes/fn/search_console.php`.
