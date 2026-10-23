---
name: visitor-rollup-tables
description: The hourly visitor summary tables (visitor_stats_hourly, visitor_content_hourly), bucket_key hashing, and how content items are identified (2026.3.4).
---

## Why not build reports by counting the raw `visitors` table?
- At 100-200k visits/day it holds millions of rows within weeks; any query grouping on `HOUR(FROM_UNIXTIME(start_timestamp))` cannot use an index and builds a temp table. The panel took 20-30 s to load and the report page never opened.

## Which summary tables exist?
Aggregated at write time (the `waf_log` pattern: bucket key + `INSERT ... ON DUPLICATE KEY UPDATE`; a repeated view bumps the counter, adds no row):
- `visitor_stats_hourly`, key `(stat_date, stat_hour)`: new sessions + page views; 24 rows/day whatever the traffic.
- `visitor_content_hourly`, key `bucket_key = sha1(date|hour|page_id|item_type|item_id)`: views per hour + page + item.
- `bucket_key` is a hash because a composite UNIQUE over the columns is ~780 bytes in utf8mb4, over the 767-byte per-column limit of MySQL 5.6 COMPACT.
- The query string never enters the key: the payload changes every request, so pagination/filter combos would defeat aggregation exactly when needed.

## How is the content item identified (not by title)?
- Old `landing_page_name` was written only on the FIRST page, after `get_page.php` stripped the slug (catalog detail, form item view): every product was `urun-detay`, every article `blog-gorunum`, so "which article at 15:00" had no answer.
- Render code reports the item it resolved with `pg_track_content($type, $id)`; `pg_record_visitor_page_view()` reads it. FIRST report wins: with several system widgets on a page, the item the URL points to must not be overwritten by a "similar products" widget below.
- Record points: product / product group (legacy catalog) `get_catalog_item_from_url()`; product (system widget `/b/<slug>`) `_render_system_widget_catalog_item_view()`; form / blog (legacy) `get_form_item_view.php`; form / blog (system widget) `_render_system_widget_form_item_view()`.
- The title is NOT copied into the row: store `item_type` + `item_id`; `pg_visitor_label_content_rows()` resolves them at report time with a batch JOIN (one query per type). A renamed product updates all history; a deleted record falls back to the page name (traffic does not vanish).
- `get_catalog_item_from_url()` is memoized: one catalog detail render calls it from several places in `get_page_content.php`, and each call used to re-run the same two queries.

## Does the rollup skip crawlers?
- No, and it also counts not-found record addresses. It feeds the dashboard, the API top content, llms.txt and the SEO impact column. The public "most read" order reads the form counters instead (`designer/record-view-counts.md`).

Related: docs/memory/visitors/visitor-item-links-and-chart-labels.md
