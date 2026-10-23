---
name: anonymous-page-cache
description: Plan (approved 2026-10-23; step 1 done in PR #1382, step 2 claimed, open): cacheability gate with standard CDN headers, then Pinegrap's own file cache for anonymous GET pages, off by default
---

## Where does this come from?
- kodpen.com, 2026-10-21: every page `Cache-Control: no-store, no-cache`, `cf-cache-status: DYNAMIC`; 0.25-0.45 s alone, 3-26 s at ~20 concurrent anonymous requests.

## Why is nothing cacheable today?
- `init.php` ~996 starts a session on every request (only `PG_NO_SESSION` skips), so PHP's `nocache` limiter sends the header and a cookieless response sets `PHPSESSID`.
- Per-visitor HTML: CSRF token (`get_token_field()`), CAPTCHA, cart and member widgets, liveform notices, language redirect (`pg_lang_redirect_page()`), `pg_consent`, mobile theme, private pages.
- A hit skips `init_tracking()` (`get_page.php` ~53) and the record view counters (`pg_sfv_count_view()`). `get_page.php` echoes `$content` at ~3256: headers can still be set there.

## What was decided?
- Route: a global open-source product with no dependence on Cloudflare; the cache works on every server.
- Primary path: Pinegrap's own file cache, off by default, TTL from settings.
- Also standard `Cache-Control` / `s-maxage` / `Vary` headers and no `Set-Cookie` on cacheable anonymous GET answers, so any CDN or reverse proxy (Cloudflare, Varnish, nginx) can cache too.
- Never cached: pages with forms, cart or member widgets; private pages; personal fragments (currency, offer code, PHP regions); non-200 answers.
- Mobile theme and consent-banner variants bypass the cache.
- Visitor statistics keep counting through a tiny beacon request.
- Schema label: step 2 = 11.0.29 (`config.page_cache_generation`).

## Steps?
- 1 — cacheability gate and standard `Cache-Control` / `s-maxage` headers for any CDN or proxy, setting off by default (done, PR #1382; `platform/page-cache-gate.md`)
- 2 — Pinegrap file cache served from the router, purge by generation number (11.0.29), visitor beacon; after 1 (claim #1380, open)

## How does the file cache work?
- `data/cache/page/<hash of host, path, variant>`, served in `router.php` after `waf_run()`, before `get_page.php`: no session, no page queries, WAF kept. Lock against stampede, sweep in the general job.
- Purge: no single save point, so a generation number bumped by every staff POST and `pg_page_schedule_run()` drops all.

## How to measure?
- Before and after: `curl -w` TTFB, 20 concurrent (`hey`/`ab`) on `/` and `/destek-merkezi`, queries per page (`perf_stats`). Check the PHP-FPM pool (`pm.max_children`) first.
