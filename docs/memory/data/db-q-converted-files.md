---
name: db-q-converted-files
description: Files whose raw queries with values now run through db_q(): which PR, how values are passed, which queries stayed raw and why, the static test that guards each file.
---

## Which files are converted, and what stayed raw?
- `get_page_content.php` (PR #2159): its 24 queries with values; values are passed `(string)` where the old SQL quoted them, so the SQL text stays the same (`escape(null)` gave `''`, a bound null would be `NULL`). The two watcher queries completed by `pg_comments_thread_sql()` stay raw: that suffix is escaped text and may hold a `?`. `tests/db_q_get_page_content_test.php` fails on an escaped value back in its query text.
- `includes/fn/content.php` (PR #2169): its 23 queries with values, `(string)` as above. Raw stay the two queries without values and those completed by a clause built with escaped values (`$where`, `$sql_where`, `$sql_comparison` of the product search), which may hold a `?`. `tests/db_q_content_test.php` guards it.
- `includes/fn/seo.php` (PR #2189): its 35 calls with values (visitor and article-view counters, visitor tracking, tag cloud, sitemap hash, perf monitor), `(string)` where the SQL quoted, `(int)` where it wrote a bare number (LIMIT, OFFSET, cursors). A clause that held an escaped value now holds `?` with its value added to the list (user agent, landing page name, perf entity type); `perf_stats` keeps its PHP-int counters inline. Raw stay the calls without values, the content backfill (`$sql_page_id` / `$sql_page_name` carry escaped names) and the integer IN lists (`$id_list`, `$page_list`, `$where`). Its `db()` / `db_value()` calls with `e()` are untouched. `tests/db_q_seo_test.php` guards it.
- `get_page.php` (PR #2227): its 29 queries with values (page lookup, membership check, the RSS and Google feeds of form list views, calendars and catalogs, the iCalendar export), `(string)` as above. The two calendar OR lists are built as `?` with a value list beside them. Raw stay the calls without values, the home page lookup (integer list `$sql_not_binned`) and the two submitted form lists of the form list view feed, completed by `$sql_where` / `$sql_filters` with escaped filter values. `tests/db_q_get_page_test.php` guards it.

## What is the rule for a query that stays raw?
- A query without values, or one completed by a clause built elsewhere with escaped values (it may hold a `?`, which `db_q()` would take as a placeholder), stays `mysqli_query()`. An integer-only IN list may stay too. Each file's test lists those clause variables by name.
- Values are passed `(string)` where the old SQL quoted them and `(int)` where it wrote a bare number, so the SQL text sent stays the same.

Related: docs/memory/data/db-q-placeholder-helper.md, docs/memory/plans/refactor-candidates.md
