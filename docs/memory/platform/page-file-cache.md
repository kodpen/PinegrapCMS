---
name: page-file-cache
description: Pinegrap's own page cache on disk (PR #1402): router serving, when a page is stored, the generation number that drops every copy, purge and sweep
---

## What does it do?
- `includes/fn/page_cache.php`, on while `PAGE_CACHE_TTL` > 0 (`page-cache-gate.md`). `PAGE_CACHE_FILE` false in `data/config.php` keeps only the headers (a CDN does the caching).
- A page the gate found cacheable is written to `data/cache/page/g<generation>/<xx>/<sha1>.html`: one JSON line (`v`, `created`, kept headers), then the body. Temp file + `rename()`; any failure is a miss, never an error.
- `router.php` calls `router_page_cache_serve()` right after `$item_name`, before the home page, files and short links. Fresh: kept headers, `s-maxage=<remaining>`, `X-Pinegrap-Cache: hit`, exit. No session, no `init.php`; `waf_run('router')` (bans, country, rate limits) has run.

## When does the router serve?
- `pg_page_cache_serve_check()`: TTL on, file cache on, `page_cache_generation` in the config row, GET/HEAD, no `?`, Host = `config.hostname` (no copy per made-up Host), no Authorization, no `pg_nc` / `software[auth]` / `pg_consent` cookie, `config.mobile` off, `maintenance_mode` off.
- Key: sha1 of scheme (HTTPS/port 443), X-Forwarded-Proto, host, path, `config.version`. Secure Mode may redirect on the proxy header, so both are in it.

## When is a page stored?
- Only where the router named it: `router_page_cache_store_here()` before the home-page and page fall-through `get_page.php` requires. Never a short link (its address draws another page) or a pattern 404.
- `pg_page_cache_store_page()` after `pg_page_cache_apply()`: gate cacheable, not `IS_BOT` / automated, 1 B-2 MB.
- Kept headers: Content-Type, Content-Language, Link, X-Robots-Tag, CSP, CSP-Report-Only, HSTS (sent by `init.php`, not by the router).

## How does a stale copy behave?
- Age < TTL fresh; < 2×TTL stale: the request that gets `<key>.lock` (`fopen 'x'`, taken over after 30 s) draws it again, the others get it with `X-Pinegrap-Cache: stale` and `s-maxage=0`. Older: a normal miss.

## How are copies dropped?
- `config.page_cache_generation` (2026.11.0, 11.0.29). Raising it moves to a new folder, so nothing older is read. `pg_page_cache_mark_dirty()` raises it once at shutdown when the status is < 400.
- Marked by: a non-GET/HEAD/OPTIONS request of a signed-in user or `integration.php` (`init.php`, `pg_page_cache_request_dirties()`; not `page_view.php`, `api.php`, `chat.php`, `software_update_check.php`, `workspace*`, MCP, OAuth); `api.php` write rows of the content/designer/commerce/shared_component/explorer handlers except add_to_cart and shipping lookups (`pg_page_cache_panel_dirties()`; dashboard polls are write rows too); `pg_announce()` of `page.*`, `product.*`, `product_group.*`, `file.*`; a product selling out in `submit_order.php`.
- Raised directly: `pg_page_schedule_run()` when it moved a page; the settings save when the TTL changes; `pg_purge_caches()` (also sets the folder aside, "Page Cache").
- Members' POSTs (cart while signed in) over-invalidate: costs hit ratio, never correctness. Stock counts after a sale, anonymous comments or records and legacy GET writes wait for the TTL.

## Who cleans the disk?
- `job.php`, `pg_page_cache_sweep_run()`: at most every 10 minutes (`data/cache/page-sweep.txt`), 10 s budget: set-aside `page.old-*`, other generations, copies older than 2×TTL, dead locks, temp files; everything when the cache is off.

## What does a hit skip?
- `init_tracking()` and record views (counted by the beacon, `visitors/page-view-beacon.md`), the perf log, `waf_run('init')` content inspection (harmless for GET without a query; a new init-phase block rule must also run in the router phase).
