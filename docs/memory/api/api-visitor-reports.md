---
name: api-visitor-reports
description: The visitor report routes under visitors:read (traffic, top-content, visitors, search, pages, ai-visibility, vitals): what each reads, the window, linked false, how pages meet Google addresses
---

## Which routes are there?
- All `GET`, scope `visitors:read`, handlers in `includes/api/resources/visitors.php`, window from `api_visitors_window($params, 400)` (from/to as datetimes, the last thirty days by default, at most 400 days).
- `reports/traffic` (hourly rollup), `reports/top-content` (content rollup), `reports/visitors` (`pg_visitor_breakdown()`, `by=channel` included), `reports/ai-visibility`, `reports/vitals`.
- `reports/search` (`SearchReport`): totals, every day, top `queries` and `pages` (`limit`, default 25) from `seo_search_daily` through `pg_gsc_totals()`, `pg_gsc_daily()`, `pg_gsc_top()`. `latest_day` is the last day Google has figures for; the asked window is answered as asked, later days are zeros.
- `reports/pages` (`PagesReport`): `pg_visitor_pages()` (`includes/fn/visitor_pages.php`, the Visitors overview's pages section), sorted by `clicks`, `visits` or `score`, `limit` default 50, `total` before the limit, `summary_days`/`summary_to` of the visit figures.

## What does linked false mean?
- The site is not linked to Search Console, or the `seo_search_daily` table is not there yet. `reports/search` answers 200 with zero totals and empty lists, `reports/pages` with every Google field null. Never 503: the Google side is optional, the visit side still answers.

## How does a page row look?
- `path` is `/` + `pg_visitor_page_key()`: no host, query, trailing slash or `.html`, decoded, lower-cased; `home` true for the root and every home page name. `page_id` null when no page has the name (a product or form address, a deleted page). Google fields and `seo_score` are null, not 0, when that side does not know the page.
- `ctr` is a fraction rounded to four places, `position` to two, as in `reports/search`.

## Why do the files load on demand?
- `search_console.php` and `visitor_pages.php` (which loads `seo.php`) are `require_once`d inside the handlers: the resource file is loaded for every API call and the OpenAPI build, and keeps only function definitions at top level (`api-openapi-declarations.md`).
