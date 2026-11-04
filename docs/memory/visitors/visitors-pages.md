---
name: visitors-pages
description: The pages section of visitors.php and the visitor_pages module: Google figures joined per page to visits, views, forms, orders and SEO score on pg_visitor_page_key()
module: visitor_pages
---

## What does the section show?
- `includes/visitors/pages.php`, `pg_visitors_section_pages()`, anchor `#pages`, heading "Pages and Google".
- One table, 25 rows: page (linked to the site address), impressions, clicks, click rate, average position, visits (as landing page), page views, forms, orders (only with `ECOMMERCE`), SEO score in its band colour (`pg_seo_score_color()`), linked to `edit_page.php?id=…`, the screen with the page's SEO panel.
- Sort switch above the table: clicks (only when linked), visits or SEO score, as links carrying `pages_sort` with the period (`pg_visitor_period_query()`), anchor `#pages`. A row without the figure sorts last.
- Rows one side alone knows stay in: a Google-only address shows 0 visits, a page Google never showed shows dashes in the Google columns (not 0).

## Where does the join live?
- `includes/fn/visitor_pages.php` (loaded by its callers, not by `functions.php`; it loads `search_console.php` and `seo.php`): `pg_visitor_page_key()`, `pg_visitor_pages_join()` (pure), `pg_visitor_pages_sort()`, `pg_visitor_pages_window()`, `pg_visitor_pages($from, $to, $google)`. Callers: the section and `GET /reports/pages` (`api/api-visitor-reports.md`). The section keeps only the drawing and its sort switch.

## How is a page matched?
- `pg_visitor_page_key($address, $home_names)`: path without scheme, host, query, fragment, slashes at either end or `.html`, `rawurldecode()`d and `mb_strtolower()`ed; `''`, `index.php`, `example.com` and every `page_home = 'yes'` name are `''` (the home page). A Google address goes through `pg_gsc_page_label()` first, which cuts the property's own path prefix.
- Landing names, content rollup `page_name` (pretty paths included) and page records go through the same function. Two Google addresses of one key (with and without the slash) add up; the position is weighted by impressions.

## Which window?
- Linked with figures: the Google window (the period's length ending on `pg_gsc_latest_day()` when that is earlier), and the visits and views read for the same days, so clicks and visits are comparable. The note under the table names the dates.
- Not linked, or no Google figures yet: the overview period, the Google columns left out, a note with a link to `search_console.php`.

## Where do the figures come from?
- Google: `pg_gsc_top('page', …, 1000)`. Visits, forms, orders: `pg_visitor_breakdown('landing_page_name', …, 1000)`. Page views: `visitor_content_hourly` summed by `page_name`; a product or form row counts on the page name it recorded.
- Scores: `page` rows whose name is a key (keys with a slash are not page names), plus the home pages; `pg_seo_row_scored()` decides whether a score is shown.

## How is it tested?
- `tests/visitors_pages_test.php`: the key on Google addresses, pretty paths, the root and its aliases, encoded Turkish letters; the join with rows one side knows; the sort; escaping and dropped columns.
