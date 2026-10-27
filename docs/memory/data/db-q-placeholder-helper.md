---
name: db-q-placeholder-helper
description: db_q($sql, $params) and its binder pg_db_bind(): how each ? is filled, what is refused, why it returns false like mysqli_query() instead of ending the request, the new-code rule.
---

## What does db_q() do?
- `db_q("SELECT id FROM page WHERE name = ? AND folder = ?", array($name, $folder_id))` in `includes/fn/core.php` fills the placeholders with `pg_db_bind()` and runs the query. Text building, not a prepared statement.
- `pg_db_bind($sql, $params, $escape)` is pure (`tests/db_q_test.php`): a string is quoted and escaped (`escape()` in `db_q()`), an int or finite float is written bare (float through `var_export()`, so no locale comma before PHP 8), a bool as 1/0, null as `NULL`. Values are taken in order; keys are ignored.
- It answers null (`db_q()`: logged, `false`) when the `?` count and the value count differ, or for an array, an object, INF or NAN.
- Every `?` in the template is a placeholder, also one inside a quoted literal: write such a literal as a bound value. A `?` inside a bound value is never expanded again.
- A numeric string stays quoted (`'42'`); cast to int first where a column compare needs a number.

## Why false on failure, not the end of the request?
- `db_q()` exists to convert the ~2,400 raw `mysqli_query()` calls (plan `refactor-candidates.md` item 4). Those read `mysqli_num_rows()` / fetch loops and carry their own `or output_error(...)` or `if (!$result)`. Returning the mysqli result and `false` like `mysqli_query()` (`MYSQLI_REPORT_OFF`) lets a caller switch without changing what it does on failure; `mysqli_error(db::$con)` still names the cause.
- So it differs from `db()` / `db_value()` / `db_items()`, which end the request through `pg_db_run()` (`db-helpers-and-error-contract.md`). It also skips the upgrade runner's `install_query_failed()` hook, as a raw call does. It returns `false` without a connection. It is counted in `pg_db_stats()` like the helpers.
- For a one-shape read where ending the request on failure is wanted, keep using `db_value()` / `db_item()` / `db_items()` with `e()`.

## Where is it used?
- New code that would call `mysqli_query()` with values uses `db_q()`. Old code switches when a file is touched for another reason; whole-file conversions go hottest first.
- Converted files: `get_page_content.php` (PR #2159): its 24 queries with values; values are passed `(string)` where the old SQL quoted them, so the SQL text stays the same (`escape(null)` gave `''`, a bound null would be `NULL`). The two watcher queries completed by `pg_comments_thread_sql()` stay raw: that suffix is escaped text and may hold a `?`. `tests/db_q_get_page_content_test.php` fails on an escaped value back in its query text.
- `includes/fn/content.php` (PR #2169): its 23 queries with values, `(string)` as above. Raw stay the two queries without values and those completed by a clause built with escaped values (`$where`, `$sql_where`, `$sql_comparison` of the product search), which may hold a `?`. `tests/db_q_content_test.php` guards it.
- `includes/fn/seo.php` (PR #2189): its 35 calls with values (visitor and article-view counters, visitor tracking, tag cloud, sitemap hash, perf monitor), `(string)` where the SQL quoted, `(int)` where it wrote a bare number (LIMIT, OFFSET, cursors). A clause that held an escaped value now holds `?` with its value added to the list (user agent, landing page name, perf entity type); `perf_stats` keeps its PHP-int counters inline. Raw stay the calls without values, the content backfill (`$sql_page_id` / `$sql_page_name` carry escaped names) and the integer IN lists (`$id_list`, `$page_list`, `$where`). Its `db()` / `db_value()` calls with `e()` are untouched. `tests/db_q_seo_test.php` guards it.
- `get_file.php` keeps its own db copies and does not get `db_q()` (`core/get-file-shared-code.md`, `tools/check_copies.php`).
