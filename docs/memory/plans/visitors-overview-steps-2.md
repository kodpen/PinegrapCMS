---
name: visitors-overview-steps-2
description: Steps V5-V11 of plans/visitors-overview.md, the sections: traffic, conversions, sources, content, Google search, pages with Google, places; claim as [plan:visitors-overview] V<n>
---

## How is a section step taken?
- Sizes and claiming: `visitors-overview-steps.md`. A section step owns its `includes/visitors/<name>.php`, its block of `pg_visitors.src.css`, its test file and its memory topic, nothing else (`visitors-overview-workers.md`). Every section is tried in a browser at 1280 and 390 px, light and dark, for today, 7 days and a month; its PR says what was tried and what the screen shows with no data.

- V5 (M) Headline and traffic section. After V1 and V2.
  - `includes/visitors/traffic.php`: tiles visits, page views, pages a visit, people on the site now, each against the previous period; the line by the period's grain with the previous period dashed; the busiest content of an hour or a day in the tooltip (`pg_visitor_top_content_by_hour()`, `pg_visitor_top_content()`); the empty state names the rollup backfill while it is still running (`pg_visitor_backfill_state()`).

- V6 (M) Conversions section. After V1 and V2.
  - `includes/visitors/conversions.php`: forms submitted, orders created, checked out and completed, revenue, conversion rate (completed over visits), each with its change; the funnel as bars; the figures by day from `pg_visitor_totals()` per day. Order tiles only with `ECOMMERCE`; with commerce off the section is forms alone.

- V7 (M) Sources section. After V1, V2 and V4.
  - `includes/visitors/sources.php`: the channel split as one stacked bar and a list with shares and changes; referring hosts, search engines with their terms, UTM source, medium and campaign, tracking and affiliate codes as top lists from `pg_visitor_breakdown()`, each with the previous period's figure; a line saying which days came from the summary.

- V8 (M) Content section. After V1 and V2.
  - `includes/visitors/content.php`: most viewed pages, products, groups and forms (`visitor_content_hourly`, labels and links as widget 5), landing pages with their visits, forms and orders (`landing_page_name` breakdown, the home page folded), site search terms; each with the previous period's figure.

- V9 (M) Google search section. After V1.
  - `includes/visitors/google.php`: linked: clicks, impressions, click rate and average position with changes over the period ending on the latest day with figures (said under the tiles), clicks and impressions by day, top queries and top pages (`pg_gsc_top()`), opportunities: queries whose impressions are above the period's median and whose click rate is under half the site's, and queries at positions 8-20 with impressions, each linking to the query in Search Console; not linked: the small card (decision 6) with one explaining line and a link to `search_console.php`. `search_console.php` is not changed.

- V10 (M) Pages joined with Google. After V2 and V9.
  - `includes/visitors/pages.php`: `pg_visitor_page_key()` (`-data.md`); one table per page: impressions, clicks, click rate, position (`pg_gsc_top('page', ...)`), visits and page views (landing-page breakdown and content rows), forms, orders, `page.seo_score` with its colour (`pg_seo_score_color()`); sortable by clicks, visits or score; rows one side alone knows stay in; links to the page and to its SEO panel. `tests/visitors_pages_test.php`: the key on Google addresses, pretty paths, the root and its aliases, encoded Turkish letters.

- V11 (S) Places and devices section. After V1 and V2.
  - `includes/visitors/places.php`: countries and cities as top lists with shares, the device split (desktop, mobile, tablet, unknown) as one bar, first against return visits; all four through `pg_visitor_breakdown()`.
