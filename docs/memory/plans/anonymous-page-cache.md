---
name: anonymous-page-cache
description: Proposal (2026-10-21, awaiting owner decision): short full-page cache for anonymous GETs; a cacheability gate first, then a Cloudflare Cache Rule (A) or a Pinegrap file cache (B)
---

## Where does this come from?
- kodpen.com, 2026-10-21: every page `cf-cache-status: DYNAMIC`, `Cache-Control: no-store, no-cache, must-revalidate`; 0.25-0.45 s alone, 3-26 s at ~20 concurrent anonymous requests, `/destek-merkezi` timed out at 30 s.

## Why is nothing cacheable today?
- The header is PHP's `nocache` session limiter: `init.php` ~996 starts a session on every request (only `PG_NO_SESSION` skips), so a cookieless response also sets `PHPSESSID`; `initialize_token()` (~1025) fills it.
- Every visitor has `PHPSESSID` after one page: bypassing on it caches only first hits, and a cached `Set-Cookie` would give everyone one session.
- Per-visitor HTML: CSRF token (`get_token_field()`: custom forms, catalog, cart, account widgets), CAPTCHA, cart and member widgets, liveform notices, first-page language redirect (`pg_lang_redirect_page()`: Accept-Language, country, `pg_language`), `pg_consent` (held-back scripts), mobile theme (`software[device_type]`), private and membership pages.
- Skipped on a hit: `init_tracking()` (`get_page.php` ~53, defined ~3299), the designed record view counters (`pg_sfv_count_view()` after the echo, `designer/record-view-counts.md`: pretty record URLs have no query string, `?r=` ones would still count), the perf log; with Cloudflare also the WAF (bans, country rule).
- `get_page.php` echoes the built `$content` at ~3256: headers can still be set there.

## Step 1 (needed by A and B): cacheability gate
- Every personal renderer above sets a flag. At ~3256 a 200 GET with no query string, no flag and no sign-in sends `Cache-Control: public, max-age=0, s-maxage=<ttl>`, drops `Set-Cookie` and the empty session; the rest stays as now. TTL setting, 0 = off (default).
- Marker cookie `pg_nc` when the session gets a cart, a sign-in or a notice.

## Option A: Cloudflare Cache Rule
- Eligible when GET/HEAD, path not under `/<SOFTWARE_DIRECTORY>/`, no `software[auth]` or `pg_nc` cookie; edge TTL from origin, browser TTL respects origin. Cloudflare ignores `Vary` on HTML: mobile and `pg_consent` variants bypass or go into the cache key (check the plan).
- Purge: `cloudflare.php` purges everything (~273); add purge by URL on save with `CLOUDFLARE_API_TOKEN`. Bans and country rule move to Cloudflare's WAF.

## Option B: Pinegrap file cache
- `data/cache/page/<sha1(host|path|device|consent)>`, served in `router.php` after `waf_run()`, before `get_page.php` (~344, ~526, ~599): no session, no page queries, WAF kept. TTL 60-300 s, lock against stampede, sweep in the general job.
- Purge: no single save point (`page.updated` comes only from the API and Workspace), so a generation number bumped by every staff POST and `pg_page_schedule_run()` drops all.

## What is recommended?
- Measure, Step 1, then A on kodpen.com; B for sites without Cloudflare or if A's hit ratio stays low. See fragment cache, item 6 of `refactor-candidates.md`.

## How to measure?
- Before and after: `curl -w` TTFB, `cf-cache-status`, 20 concurrent (`hey`/`ab`) on `/` and `/destek-merkezi`, queries per page (`perf_stats`). 3-26 s at 20 concurrent suggests queuing for PHP workers (`pm.max_children`): check the pool first.

## What must the owner decide?
1. A, B or both; TTL; off by default elsewhere?
2. Pages with forms: stay dynamic, or token by a small fetch?
3. Visitor stats and record view counts (most read) on hits: accept the loss, or a beacon?
4. Mobile and consent variants: bypass or cache key?
