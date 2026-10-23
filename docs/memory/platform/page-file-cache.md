---
name: page-file-cache
description: Pinegrap's own page cache on disk (PR #1402): router serving, the key, when a page is stored, stale copies, the sweep, what a hit skips
---

## What does it do?
- `includes/fn/page_cache.php`, on while `PAGE_CACHE_TTL` > 0 (`page-cache-gate.md`). `PAGE_CACHE_FILE` false in `data/config.php` keeps only the headers (a CDN does the caching).
- A page the gate found cacheable is written to `data/cache/page/g<generation>/<xx>/<sha1>.html`: one JSON line (`v`, `created`, kept headers), then the body. Temp file + `rename()`; any failure is a miss, never an error.
- `router.php` calls `router_page_cache_serve()` right after `$item_name`, before the home page, files and short links. Fresh: kept headers, `s-maxage=<remaining>`, `X-Pinegrap-Cache: hit`, exit. No session, no `init.php`; `waf_run('router')` (bans, country, rate limits) has run.

## When does the router serve?
- `pg_page_cache_serve_check()`: TTL on, file cache on, `page_cache_generation` in the config row, GET/HEAD, no `?`, Host = `config.hostname` (no copy per made-up Host), no Authorization, no `pg_nc` / `software[auth]` / `pg_consent` cookie, `config.mobile` off, `maintenance_mode` off.
- Key: sha1 of scheme (HTTPS/port 443), whether a proxy says https (the headers of `check_proxy_ssl_headers()`, reduced to `https` or ''), host, path, `config.version`. Secure Mode may redirect on the proxy's word, so both are in it; a made-up header value draws no copy of its own.

## When is a page stored?
- Only where the router named it: `router_page_cache_store_here()` before the home-page and page fall-through `get_page.php` requires. Never a short link (its address draws another page) or a pattern 404.
- `pg_page_cache_store_page()` after `pg_page_cache_apply()`: gate cacheable, not `IS_BOT` / automated, 1 B-2 MB.
- Kept headers: Content-Type, Content-Language, Link, X-Robots-Tag, CSP, CSP-Report-Only, HSTS (sent by `init.php`, not by the router).

## How does a stale copy behave?
- Age < TTL fresh; < 2×TTL stale: the request that gets `<key>.lock` (`fopen 'x'`, taken over after 30 s) draws it again, the others get it with `X-Pinegrap-Cache: stale` and `s-maxage=0`. Older: a normal miss.

## How are copies dropped?
- `config.page_cache_generation` (2026.11.0, 11.0.29): raising it moves to a new folder, so nothing older is read. Which writes raise it: `page-cache-invalidation.md`.

## Who cleans the disk?
- `job.php`, `pg_page_cache_sweep_run()`: at most every 10 minutes (`data/cache/page-sweep.txt`), 10 s budget: set-aside `page.old-*`, other generations, copies older than 2×TTL, dead locks, temp files; everything when the cache is off.

## What does a hit skip?
- Setting `pg_nc`: a visitor whose session turned personal before the switch-on sees stored copies until a page is drawn for them (`page-cache-gate.md`).
- `init_tracking()` and record views (counted by the beacon, `visitors/page-view-beacon.md`), the perf log, `waf_run('init')` content inspection (harmless for GET without a query; a new init-phase block rule must also run in the router phase).
