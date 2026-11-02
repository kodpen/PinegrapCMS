---
name: page-vitals-beacon
description: Real-user LCP/INP/CLS beacon into page_vitals_hourly (11.0.149, #5040): which pages carry it, day-signed page id, rate limit, validation, buckets, upsert, 400-day sweep
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
- `vp` is handled before the page-view payload and exits. First `pg_vitals_rate_exceeded()`: at most `PG_VITALS_RATE_LIMIT` (30) beacons a minute per address (`waf_rate_exceeded(waf_ip_subject(ip), 'vitals', …)`).
- `pg_vitals_verify()`: HMAC-SHA256 of `'page-vitals:' . page_id . '|' . day` over `ENCRYPTION_KEY`, the day the page was drawn; today and yesterday are accepted (a page drawn before midnight or kept by the page cache, an hour at most), an older one is refused. Why (#5397 review): a signature only over the page id never expired, so anyone could copy it from a page and post false figures in a loop.
- `pg_vitals_parse()` drops the whole beacon for a bad shape, an unknown device, a non-integer, more than six digits, LCP > 60000, INP > 10000, CLS > 10000, or nothing measured.
- `pg_vitals_record()`: not for `pg_request_is_automated()`; site time zone `date('Y-m-d')` and `date('G')`; one `INSERT … ON DUPLICATE KEY UPDATE` from `pg_vitals_upsert_sql()`.

## What does the row hold?
- `page_vitals_hourly` (`upgrade_2026_11_0_page_vitals_hourly()`, in `get_tables()`): `id` = `sha1("day|hour|page_id|device")`, `views` (beacons), per figure `<m>_sum` and `<m>_good/_needs/_poor`. An unmeasured figure adds to none of its columns, so a figure's sample count is the sum of its three buckets, not `views`.
- Buckets (`pg_vitals_bucket()`): good ≤ 2500 / 200 / 100, poor > 4000 / 500 / 250 (LCP ms, INP ms, CLS × 1000); the threshold itself is in the better bucket.
- Derived data: wrong rows are truncated and refill, never fixed (`visitor-rollup-consistency.md`).

## How long are rows kept?
- 400 days (`PG_VITALS_DAYS`): `job.php` runs `pg_vitals_sweep()` once a day (`data/temp/page_vitals_sweep.json`), `DELETE … WHERE day < ? LIMIT 5000` within 5 seconds, as `site-search-rollup.md`.

## What comes later?
- WV2: figures (p75 from buckets and sums) and `reports/vitals`. `plans/web-vitals-ai-visibility-steps.md`.

## Tests?
- `tests/vitals_beacon_test.php`.
