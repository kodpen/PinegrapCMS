---
name: sitemap-cache
description: sitemap.xml kept in data/cache/sitemap.txt under config.page_cache_generation with a one-hour lifetime (#2054): which writes redraw it, what waits for the lifetime, the lock, the purge
module: seo
---

## Why is sitemap.xml kept between requests?
- `get_sitemap_info()` reads every public page, product, group, record and event: 5.5-6.3 s per request on a large site (issue #2054), and anyone could ask for it again and again.
- `pg_sitemap_info_cached()` (`includes/fn/seo.php`) keeps the drawn file in `data/cache/sitemap.txt`: one JSON line (`v`, `created`, `generation`, `version`, `urls_exist`), then the XML. Written with `pg_page_cache_write()` (temp + rename); a cut or foreign file is a miss. `get_sitemap.xml.php` and `update_sitemap_and_ping()` read through it; the answer says `X-Pinegrap-Cache: hit|stale|miss|off`.
- The sitemap does not depend on the request: scheme and host come from `config` (`URL_SCHEME`, `HOSTNAME_SETTING`), so one copy serves everyone. The stylesheet line is added after the copy is read.

## What draws it again?
- `config.page_cache_generation` differs from the one it was kept under. Every write that may change what pages show raises it: `page-cache-invalidation.md` (signed-in POSTs, panel write actions, MCP writes, `pg_announce()` of pages, products, groups and files, page schedule, the purge). Since #2797 `pg_page_cache_mark_dirty()` raises it while the page cache is off too (one UPDATE per such request).
- This covers every switch the sitemap reads: page add/edit/bulk screens (sitemap, noindex, folder, archive), folders and their access, products and groups, catalog and list records saved in the panel, calendar events, Settings (additional sitemap content, hostname, scheme), Settings > Languages and the translation writes that drop index answers.
- The generation is read before drawing, so a write during the 6 s draw leaves the copy under an older number: drawn again on the next request.
- Another `VERSION` (an upgrade may change what is listed): miss.
- Older than `PG_SITEMAP_CACHE_TTL` (3600 s), or created more than a minute in the future (clock set back).

## Which changes wait for the hour?
- Not safe through the number, so bounded by the lifetime: records and comments sent by anonymous visitors, a translated page's index answer kept when it is drawn (GET, `translation-index-verdict.md`), legacy GET writes with a token, Workspace endpoints that announce nothing, changes made straight in the database or by a restore. Hooking each one was not worth it: search engines read the sitemap a few times a day.

## What do concurrent requests do?
- Stale copy: the request that takes `sitemap.txt.lock` (`pg_page_cache_lock()`, taken over after 30 s) draws it, the others get the old copy. No copy at all: each request draws (first request after an upgrade or purge).
- Before the 2026.11.0 step adds `page_cache_generation` the cache is off and every request draws, as before.

## How is it emptied?
- `pg_purge_caches()` deletes the file ("Sitemap" in the cleared list); the purge raises the generation anyway.
- Tested in `tests/sitemap_cache_test.php` (file, states, that the mark does not wait for the page cache).

Related: docs/memory/platform/page-cache-invalidation.md, docs/memory/visitors/sitemap-listed-pages.md
