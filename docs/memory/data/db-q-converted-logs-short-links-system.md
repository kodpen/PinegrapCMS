---
name: db-q-converted-logs-short-links-system
description: Short link screens and module, comment toggle, search index, audit trail, privacy retention, dashboard and sessions converted to db_q(): special cases, what stayed raw and why, guard test.
---

## Which files are converted?
- `edit_short_link.php` (6 calls) and `add_short_link.php` (4): the lookups, the delete, the update and the insert. The optional pieces built before the query (`$short_link_sql_answer`, `$sql_value`) hold `?` and gather their values in `$short_link_answer_values` / `$sql_values`, merged in order with `array_merge()`. Answer columns and `expires_at` stay quoted (`(string) (int)`), as before.
- `includes/fn/short_link_redirects.php` (3): the name key read binds each name after the `_utf8mb4` introducer (`CONVERT(_utf8mb4? USING …)`: the bound text is `_utf8mb4'name'`, as before); the pattern read binds the `expires_at` time only when `$open` is in the SQL; the use counter binds time and id quoted. These functions run only where `functions.php` is loaded; the router alone calls only the pure `pg_short_link_location()` / `pg_short_link_status()` / `pg_short_link_path_name()`.
- `allow_or_disallow_new_comments.php` (4), `update_search_index.php` (2), `includes/panel/dashboard.php` (1), `view_sessions.php` (1): plain quoted values.
- `includes/fn/audit.php`: the `audit_log` INSERT (a null bound as `NULL`, every other value quoted, an int too) and `_pg_audit_fetch_sql()`, which takes the values of its SQL: the read by ids binds the ints bare (`IN (1,2)`), the read by column the value quoted.
- `includes/fn/privacy_retention.php` (2): the audit trim (`created_at < ?` quoted, `LIMIT ?` bare) and the consent delete (`IN (?, ?)` with the ids as strings, quoted as before).

## What stayed raw?
- No values: `view_comments.php` count, `clean_up.php`, `software_update_check.php`, `software_update.php`, `includes/panel/software.php`, `welcome.php`, `includes/fn/system_status.php`, the short link collation read, the perf log truncates and the log counts.
- Completed by a clause built elsewhere: `view_performance_log.php` (`$stats_where`, `$where`, the order variables), `view_log.php` lists (`$order_by`; the timestamps are ints), `view_short_links.php` (`$sql_live`), `view_comments.php` list (`$where`).
- `db()` calls in `dashboard.php` and `short_link_redirects.php` with `escape()` are helper calls, outside this conversion.

## What does the guard test check?
- `tests/db_q_logs_short_links_system_test.php`: no escaped value in a `db_q()` SQL or a raw query (clauses listed by name), no `escape()` / `e()` / `mysqli_real_escape_string` left in the short link screens, the comment toggle, audit and retention, one value per `?` in each literal list, and the bound text of the name key, use counter, retention deletes, audit insert and reads, and the short link update.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-placeholder-helper.md
