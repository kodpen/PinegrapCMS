---
name: site-search-rollup
description: site_search_daily (11.0.147), the per-day search rollup: who counts, once per visit, the signed result click through page_view.php, the 400-day sweep.
module: site_search_stats
---

## What does the table hold?
- `site_search_daily` (`upgrade_2026_11_0_site_search_daily()`, decision #5047): `id` CHAR(40) = `sha1("Y-m-d|term")`, `day`, `term` VARCHAR(191), `searches`, `zero_results`, `clicks`; `idx_day (day, searches)` for the report's top terms. One `INSERT … ON DUPLICATE KEY UPDATE` per count (`pg_site_search_upsert_sql()`), by `visitors/high-volume-counter-rules.md`. Listed in `get_tables()`.
- The term is `pg_site_search_term()`: lower case (İ → i, ı stays), whitespace folded, at most 100 characters, '' without a letter or digit. Days are PHP `date('Y-m-d')`, the site's time zone.

## Who counts a search?
- The search results widget (`_render_system_widget_search_results()`, first results page only, with the shown total after `offset`) and the legacy `get_search_results.php` (featured + catalog + pages), both through `pg_site_search_count($query, $results)`; 0 results raises `zero_results` too.
- Once a day per visit and term: the session keeps the counted keys (`$_SESSION['software']['site_search_seen']`, last 200, `pg_site_search_seen_add()`); without a session every request counts.
- Not counted: crawlers and scripts (`pg_request_is_automated()`), the SEO render and the search index render, a site that has not run 11.0.147 (`pg_site_search_stats_ready()`).
- Counted whether visitor tracking is on or not: the rollup holds no visitor data. The visit's own term list (`pg_search_record_visit_term()`) still follows the switch.

## How is a result click counted?
- The widget appends `pg_site_search_click_script_for()` after its results: a document click listener that, for a link whose `href` is one of the shown result addresses, posts `sc` (term), `ss` (HMAC-SHA256 of `'search-click:' . term` over `ENCRYPTION_KEY`) and `u` (the href) to `page_view.php` with `sendBeacon`, else `fetch` keepalive. No key, no script.
- `page_view.php` handles `sc` before the page-view payload and exits: a valid signature calls `pg_site_search_click()`, once a day per visit, term and address. The signature only proves the term was searched on this site; a replay counts like a reload, within the session's once rule.
- The legacy Search Results page counts searches but no clicks.

## How long are rows kept?
- 400 days (`PG_SITE_SEARCH_DAYS`): `job.php` runs `pg_site_search_sweep()` once a day (`data/temp/site_search_sweep.json`), `DELETE … WHERE day < ? LIMIT 5000` within 5 seconds.

## Where is the report?
- The Site Search section of the Visitors overview, `visitors.php#site_search` (`includes/visitors/site_search.php`, V17 #6475), over the overview's period: `pg_site_search_figures($from, $to)` reads, `pg_site_search_report($figures, $may_tune)` draws (pure). Both SQL statements bind the first and the last day (`day >= ? AND day <= ?`). Nothing is drawn until `pg_site_search_stats_ready()`.
- Tiles: searches, terms, no results, result clicks, clicks per search (`pg_site_search_click_rate()`, can pass 100%: several results of one search may be clicked; '-' with no search). Tables: top 100 terms by searches, top 100 terms that found nothing (`pg_site_search_terms_sql()`; rows summed, `GROUP BY term`), 5 of each shown at first (`pg_visitors_table()` `limit`).
- A manager (role < 3) gets a promote button per zero-result term: `settings_features.php?search_promote=<term>#pgset-search-tuning`, where the card adds a `term => ` line (`pg_search_promoted_add_line()`) for the operator to finish and save.
- `visitor_search.php` only answers a 301: its `days` (7/30/90/365, default 30) become `p=7d|30d|90d`, a year a custom range ending today (`pg_site_search_overview_query()`).

## Where are synonyms and promoted results?
- `designer/search-synonyms-promoted.md`.
