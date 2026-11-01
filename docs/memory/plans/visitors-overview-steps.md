---
name: visitors-overview-steps
description: Steps V1-V4 of plans/visitors-overview.md, the foundations: shell and period, figures module, daily summary every day, channel and search engines; claim as [plan:visitors-overview] V<n>
---

## How are the steps taken?
- Sizes: S under 400 product lines, M about 700, L about 1,000. Claim with a draft `[plan:visitors-overview] V<n> — <topic>`; read `visitors-overview-design.md` and `-data.md` first, then the memory the plan names. `free` is claimable now; "After Vn" once Vn is merged. Sections V5-V11: `visitors-overview-steps-2.md`; V12-V16: `-steps-3.md`; who takes what: `visitors-overview-workers.md`.

- V1 (L) Shell, period and menu. `free`.
  - `visitors.php`, `includes/visitors/shell.php`, `includes/fn/visitor_periods.php`, `assets/js/pg_visitors.src.js` and `.min.js`, `assets/css/pg_visitors.src.css` and `.min.css` with the empty section blocks, both registered in their bundle lists; the period bar with the compare switch and the custom range (`pgDatePicker`); the section loader; the menu, panel search and help entries (`-design.md`). With no section file yet the screen shows the period bar and one line saying the sections are coming. `tests/visitor_periods_test.php`: bounds of every preset, Monday weeks, a DST change, a custom range, the same-hour cut, bad input. Memory: `ui/visitors-screen.md`.

- V2 (M) Figures module. `free`.
  - `includes/fn/visitor_figures.php`: `pg_visitor_totals()`, `pg_visitor_breakdown()`, `pg_visitor_summary_range()`, `pg_visitor_day_sources()`, wrappers over the content rollup for a window and a grain; the merge of summary and raw rows taken out of `includes/api/resources/visitors.php`, whose `reports/visitors` now calls the module and answers as before plus `summary_days`. `tests/visitor_figures_test.php`: the day split with a run in the middle, at the end and absent; the merge by value; the rest row. Memory: `visitors/visitor-figures-module.md`.

- V3 (M) Daily summary every day. `free`.
  - `includes/fn/visitor_retention.php`: `pg_visitor_cleanup_due()` and `pg_visitor_cleanup_run()` summarize as `-data.md` says (yesterday first, backwards within the budget, the last two days written again, independent of `days`); `pg_visitor_rows_from()`; the removal untouched. `view_visitor_report.php`: the summary condition and the detail query use `pg_visitor_rows_from()`; its summary notice names the right day. The Visitor tracking settings card's text for the summary switch. Tests on the day order a run picks (pure). Memory: `visitors/visitor-retention-and-cleanup.md` corrected.

- V4 (M) Channel and search engines. After V2 and V3.
  - `includes/fn/seo.php`: the engine table and `pg_visitor_search_engine($host)` in place of the if-chain of `init_tracking()`, Google by host; `includes/migrations/2026.11.0.php`: one sub-step appending the nine engines to the `referring_search_engine` ENUM (`install_modify_column()` after reading the type, as 2026.4.10 did for `device`; the next free label when pushed), the two install dumps; `pg_visitor_channel_sql()` as the full rule with its two host lists, `channel` in `pg_visitor_summary_dimensions()`, in `api_visitors_dimensions()` and the summary map of the API. `tests/visitor_channel_test.php`: referrers of every kind, a host with a country suffix, the precedence. Memory: `visitors/visitor-channel-rule.md`.
