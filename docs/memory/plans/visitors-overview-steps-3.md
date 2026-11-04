---
name: visitors-overview-steps-3
description: Steps V12-V17 of plans/visitors-overview.md: alerts, saved reports tidy-up, API routes, status lines, close-out, the speed, AI and site search sections; claim as [plan:visitors-overview] V<n>
---

## Which steps are there?
- Sizes and claiming: `visitors-overview-steps.md`; what a step owns: `visitors-overview-workers.md`.

- Done: V13 #6385, V15 #6381, V17 #6475, V12 #6505

- V12 (M) Alerts section. Done (#6505). After V5, V6 and V9 (it reads their figures through the module and `pg_gsc_*`, not their files).
  - `includes/visitors/alerts.php`, drawn first on the screen: from the period and its previous one: visits down by more than 30 %; a landing page that lost more than half its visits (at least 50 before); the conversion rate halved; Google clicks down by more than 30 %; a page that lost more than half its clicks; no visit recorded today after 10:00 (tracking broken); each one line with its figures and a link to its section; nothing drawn when there is nothing. Thresholds are constants in the file; `tests/visitors_alerts_test.php` on the rules as pure functions over figure arrays. No table, no job (decision 7).

- V14 (S) API routes. After V2, V4 and V10. In progress.
  - `includes/api/resources/visitors.php`: `reports/visitors` gains `by=channel`; new `reports/search` (totals, by day, queries, pages from `seo_search_daily`; `linked: false` when not linked) and `reports/pages` (V10's join), declared in `api_schema()` with the reports scope, `php tools/check_api_schema.php` green. Memory: `api/api-visitor-reports.md`.

- V17 (M) Speed, AI and site search sections. After V1.
  - `includes/visitors/speed.php`, `ai.php`, `site_search.php` call `pg_visitor_speed_section()`, `pg_visitor_ai_section()` (`includes/fn/visitor_insights.php`) and the site search report's drawing, taken out of `visitor_search.php` into a function in `includes/fn/site_search_stats.php`, with the overview's period; each draws nothing until its ready check (`pg_vitals_ready()`, `pg_ai_visibility_ready()`, the search table). `visitor_vitals.php`, `visitor_ai.php`, `visitor_search.php` answer a 301 to their anchor with the period kept; their flyout entries become anchors (V1's list). Memory: `ui/visitors-speed-section.md`, `ui/visitors-ai-section.md`, `visitors/site-search-rollup.md` corrected.

- V16 (S) Close-out. After V1-V15 and V17.
  - A phone pass of the whole screen (390x844, 844x390, touch), keyboard, reduced motion, every section in light and dark; the lasting decisions moved into `visitors/` and `ui/` topics, the key rule "a visitor figure comes from `visitor_figures.php`, never a raw count" into `visitors/README.md`; the seven plan files deleted; the PR names the `[NEW]` changelog line.
