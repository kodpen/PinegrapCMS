---
name: page-vitals-beacon
description: Real-user LCP/INP/CLS from pg_vitals.min.js into page_vitals_hourly (11.0.149, decision #5040): which pages carry it, the signed page id, the v= part, validation, buckets, the upsert
module: page_vitals
---

## What is measured, and how is it sent?
- `assets/js/pg_vitals.src.js` (bundle `pg_vitals.min.js`): LCP (last `largest-contentful-paint` entry), INP (longest `event` duration with an `interactionId`, `durationThreshold` 40, plus `first-input`), CLS (largest session window: shifts < 1 s apart, window < 5 s, no recent input). All observers `buffered: true`.
- Sent once on `visibilitychange` hidden or `pagehide` with `navigator.sendBeacon`: `vp` page id, `vs` signature, `v` = `lcp,inp,cls,device` (ms, ms, CLS × 1000; an unmeasured figure is empty). Device by `innerWidth`: < 768 mobile, < 1024 tablet, else desktop. A page back from the back-forward cache sends nothing more (`pageshow` persisted). No `sendBeacon`, no figures.
- The connection kind the plan named is not sent: #5040's table has no column for it.

## Which pages carry it?
- `get_page.php` calls `pg_vitals_beacon_inject()` after `pg_version_assets()` and before the page cache, so the stored copy carries it and cached visits are measured too.
- Only when `pg_vitals_beacon_wanted()`: the HTML branch (not PDF/RSS/iCal), no panel bar (`$toolbar`), not the File Manager preview (`pg_preview=1`), not a background render (`pg_seo_rendering()`), not a staff user (role < 3, also with `edit=no`). The designer canvas and prototype play draw through other entry points and never reach this.
- Also needs `pg_vitals_ready()` (the table), `ENCRYPTION_KEY` and a `</body>`. Independent of visitor tracking: nothing personal is kept.

## What does page_view.php do with it?
- `vp` is handled before the page-view payload and exits. `pg_vitals_verify()`: HMAC-SHA256 of `'page-vitals:' . page_id` over `ENCRYPTION_KEY`, so only pages this site signed are counted; a replay counts like a reload.
- `pg_vitals_parse()` drops the whole beacon for a bad shape, an unknown device, a non-integer, more than six digits, LCP > 60000, INP > 10000, CLS > 10000, or nothing measured.
- `pg_vitals_record()`: not for `pg_request_is_automated()`; site time zone `date('Y-m-d')` and `date('G')`; one `INSERT … ON DUPLICATE KEY UPDATE` from `pg_vitals_upsert_sql()`.

## What does the row hold?
- `page_vitals_hourly` (`upgrade_2026_11_0_page_vitals_hourly()`, in `get_tables()`): `id` = `sha1("day|hour|page_id|device")`, `views` (beacons), per figure `<m>_sum` and `<m>_good/_needs/_poor`. An unmeasured figure adds to none of its columns, so a figure's sample count is the sum of its three buckets, not `views`.
- Buckets (`pg_vitals_bucket()`): good ≤ 2500 / 200 / 100, poor > 4000 / 500 / 250 (LCP ms, INP ms, CLS × 1000); the threshold itself is in the better bucket.
- Derived data: wrong rows are truncated and refill, never fixed (`visitor-rollup-consistency.md`).

## What comes later?
- WV2: figures (p75 from buckets and sums), `reports/vitals`, and the 400-day sweep by the general job; until then rows are not removed. `plans/web-vitals-ai-visibility-steps.md`.

## Tests?
- `tests/vitals_beacon_test.php`.
