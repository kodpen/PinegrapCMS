---
name: web-vitals-ai-visibility-steps
description: Steps WV1-WV3 of plans/web-vitals-ai-visibility.md: the vitals beacon and rollup, the figures and API route, the AI fetch counter; claim as [plan:web-vitals-ai-visibility] WV<n>
---

## Which run side by side?
- Wave 1: WV1 and WV3 at once (different files) after questions 1-2. Wave 2: WV2 and WV4 at once. Wave 3: WV5. Wave 4: WV6. WV4-WV6: `web-vitals-ai-visibility-steps-2.md`.
- S under 400 product lines, M about 1,000. Tests in `tests/vitals_<topic>_test.php` and `tests/ai_visibility_<topic>_test.php` (pure functions), memory under `visitors/`, texts through `tr_add.php`, `php tools/build_js.php` after the script.

## Which steps are there?
- WV1 (M) Beacon and rollup. `free` after question 1.
  - `assets/js/pg_vitals.src.js` and `.min.js` (registered in `pg_js_bundles()`): LCP, INP and CLS through `PerformanceObserver` (`buffered: true`), the final values sent once on `visibilitychange` hidden or `pagehide` with `navigator.sendBeacon()`; no value for a page restored from the back-forward cache. `output.php` prints it for public pages only (not the designer, Preview, prototype, or a staff session). `page_view.php` reads `v=` (three numbers, device class, connection) after the token check, validates ranges (LCP 0-60 s, INP 0-10 s, CLS 0-10) and writes the schema sub-step's table `page_vitals_hourly` (`install_create_table()`, in `get_tables()`, rerun twice) with `INSERT ... ON DUPLICATE KEY UPDATE`: counts, sums, good/needs-work/poor buckets per Google's thresholds. Tests: the bucket function, the validation, the key. Memory: `visitors/page-vitals-beacon.md`.

- WV2 (M) Figures and API. After WV1.
  - `includes/fn/page_vitals.php`: `pg_vitals_site($from, $to)` (p75 estimated from the buckets and sums, the good share), `pg_vitals_pages($from, $to, $order, $limit)` (slowest first), `pg_vitals_trend()` (per day); the API route `reports/vitals` in `includes/api/resources/reports.php` with its schema row (`api/api-endpoint-rules.md`), MCP tool follows; the general job keeps 400 days. Tests on the p75 estimate against a known distribution. Memory: `visitors/page-vitals-figures.md`.

- WV3 (M) AI fetch counter. `free` after questions 1-2.
  - In the firewall's bot path (`waf_handle_bot()`, after the verdict): a verified AI fetcher or indexer, and with question 2 an unverified AI token, counts into `ai_fetch_daily` (schema sub-step, key `sha1(date|bot|page_id)`, `bot` the catalogue name, `page_id` from the router when the request is a page, 0 otherwise) through the counter rules of `visitors/high-volume-counter-rules.md`; no row for a blocked request. Tests: the counting decision per verdict and class. Memory: `visitors/ai-fetch-counter.md`.
