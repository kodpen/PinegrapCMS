---
name: db-q-converted-files
description: Files whose raw queries with values run through db_q() (get_page_content, content, seo, get_page, ecommerce), the casts used, which calls stay raw and why, the guard tests.
---

## Which rule does every conversion follow?
- A value goes in as `(string)` where the old SQL quoted it, so the SQL text stays the same (`escape(null)` gave `''`; a bound null would be `NULL`), and as `(int)` where it wrote a bare number.
- A query completed by a clause built elsewhere with escaped values stays raw: that clause may hold a `?`, which `db_q()` would take for a placeholder (`db-q-placeholder-helper.md`).
- Each file has a static test that fails on an escaped value back in its query text.

## Which files are converted?
- `get_page_content.php` (PR #2159): its 24 queries with values, `(string)`. The two watcher queries completed by `pg_comments_thread_sql()` stay raw: that suffix is escaped text and may hold a `?`. `tests/db_q_get_page_content_test.php` fails on an escaped value back in its query text.
- `includes/fn/content.php` (PR #2169): its 23 queries with values, `(string)` as above. Raw stay the two queries without values and those completed by a clause built with escaped values (`$where`, `$sql_where`, `$sql_comparison` of the product search), which may hold a `?`. `tests/db_q_content_test.php` guards it.
- `includes/fn/seo.php` (PR #2189): its 35 calls with values (visitor and article-view counters, visitor tracking, tag cloud, sitemap hash, perf monitor), `(string)` where the SQL quoted, `(int)` where it wrote a bare number (LIMIT, OFFSET, cursors). A clause that held an escaped value now holds `?` with its value added to the list (user agent, landing page name, perf entity type); `perf_stats` keeps its PHP-int counters inline. Raw stay the calls without values, the content backfill (`$sql_page_id` / `$sql_page_name` carry escaped names) and the integer IN lists (`$id_list`, `$page_list`, `$where`). Its `db()` / `db_value()` calls with `e()` are untouched. `tests/db_q_seo_test.php` guards it.
- `get_page.php` and `includes/fn/ecommerce.php` (PR #NNNN): 27 and 100 calls with values, `(string)` where the SQL quoted (also where it wrote an unescaped value in quotes, now escaped), `(int)` where it wrote a bare number (`$discounted_by_offer`, the zone copy of `duplicate_product()`). The restored-price UPDATE of `apply_offers_to_cart()` builds `$values` beside its optional `price = ?`. Raw stay the calls without values and those completed by a clause built elsewhere: `$sql_where`, `$sql_viewer_filter`, `$sql_filters` / `$sql_field_joins` (RSS form lists), `$where_calendar_id`, `$sql_scope`, the products INSERT of `duplicate_product()`. `tests/db_q_get_page_ecommerce_test.php` also counts each call's `?` against its values.
