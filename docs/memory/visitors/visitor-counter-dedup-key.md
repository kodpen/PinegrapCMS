---
name: visitor-counter-dedup-key
description: Why http_referer is not part of the visitor dedup key in init_tracking(), how the visitor IP is resolved, and the defensive user_agent column probe (2026.2.5).
---

## Why is `http_referer` not in the dedup key?
- `init_tracking()` runs a "same visitor in the last 30 minutes?" query that only clients WITHOUT cookies reach, i.e. almost entirely automated traffic. Such a client sends a different referer on every page, so the match never held and EVERY request opened a new visitor row. One scraper walking a blog made tens of thousands of "visitors", which is why the site counter ran far ahead of Google Analytics (GA is JavaScript-based and counts no bots).
- Campaign separation still works: `tracking_code` and `affiliate_code` stay in the key.
- `http_referer` is still WRITTEN to the record, it is just not a matching criterion.
- Several rows can match, so the query has `ORDER BY stop_timestamp DESC LIMIT 1` (most recent active record wins).

## How is the visitor IP resolved?
- With `waf_client_ip()`. Behind a CDN `REMOTE_ADDR` is the edge server and all visitors collapsed into a few addresses. With no trusted proxy defined it returns `REMOTE_ADDR`, so plain hosting behaves exactly as before.

## How is the `user_agent` column written?
- With a defensive probe (`waf_table_has_column`): on an install with new code but un-upgraded DB the page view does not error, the column is silently skipped.

Diagnostic queries: `docs/ziyaretci-sayaci-teshis.sql`
