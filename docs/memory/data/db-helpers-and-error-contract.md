---
name: db-helpers-and-error-contract
description: The db helpers (db, db_value, db_values, db_item, db_items, e, escape_like) and what a failed query does: helpers end the request, raw mysqli_query() and db_q() return false.
---

## Which DB helpers exist?
- In `includes/fn/core.php`: `db_value('SQL')` one value, `db_values()` one column as a list, `db_item()` one row, `db_items('SQL', $key_column = '')` all rows as an array: walk it with `foreach`, never `mysqli_fetch_assoc()`. `get_file.php` keeps its own copies of `db()`, `db_value()`, `db_item()`, `db_items()` and `escape()` (`core/get-file-shared-code.md`).
- `db('SQL')` runs anything and shapes its answer by the result: `true` for a write, `''` for no row, the value for one row of one column, the row for one row, a list for more. A loop over `db("SELECT ...")` breaks when exactly one row comes back; read with a helper of one shape.
- `db_q($sql, $params)`: `?` placeholders, `false` on failure; new code uses it, not `mysqli_query()` (`db-q-placeholder-helper.md`).
- `e($val)` escapes a quoted SQL value (`escape()` is the older name; null gives `''`): `WHERE name = '" . e($name) . "'`.
- LIKE: `e(escape_like($val))`. `escape_like()` puts a backslash before `%` and `_` and escapes no quote, so alone it is an SQL injection. A search box's "contains" pattern comes from `pg_list_like_pattern($term)` (`includes/fn/list_paging.php`: `%…%`, the backslash escaped too), bound with `db_q()` or put through `e()`. A raw `'%' . $term . '%'` let a typed `%` or `_` match anything; the orders, contacts, submitted forms, comments, themes lists and header search use it since 2026.11.0 (#2756, order number #3650; `tests/list_like_sites_test.php`).

## Does a value from a constant need e()?
- Yes, unless it is a number. `USER_EMAIL_ADDRESS` holds the address as stored, and `validate_email_address()` accepts `'`: the watcher queries of add_or_remove_watcher.php and the classic comments block quoted it raw until 2026.11.0, so an apostrophe gave "Query failed." and a crafted address made the self-remove DELETE drop every e-mail watcher of the thread. `tests/comments_test.php` scans the tree for it.

## What does a failed query do?
- A raw `mysqli_query()` returns `false` and never throws: `init.php`, `router.php` and `install/index.php` call `mysqli_report(MYSQLI_REPORT_OFF)`. NEVER remove those lines. The code is written to that contract (`or output_error(...)`, `if (!$result)`, a leading `@`). Since PHP 8.1 mysqli throws by default; none of those checks sees an exception, so every SQL error became an empty-body 500 with the cause only in error.log.
- The helpers never return `false`: `pg_db_run()` writes the MySQL text to the error log and ends the request with `output_error('Query failed.')`. `output_error()` shows the text only with `DEBUG` (Verbose Database Errors) or during install/upgrade, otherwise a hint to turn that setting on (#951; before it the text was in every visitor's message). Under `pg_error_throws(true)` (JSON endpoints such as `settings_pane.php`) or in the SEO pass (`pg_seo_rendering()`) `output_error()` throws a `RuntimeException` instead.
- While the upgrade runner runs, `install_query_failed()` decides first: "already applied" errors (1050, 1060, 1061, 1091, 1146 on DROP) make the helpers answer empty, any other error throws `InstallQueryException` (`release/upgrade-runner-behavior.md`).
- So a query that may fail on purpose never goes through a helper to find out: probe a table or column a migration adds first (`release/schema-probe-rules.md`). Code that must live through a failure (`includes/fn/innodb.php`, `includes/fn/db_maintenance.php`) calls `mysqli_query()` and reads `mysqli_errno()` / `mysqli_error(db::$con)`.

## Which table names?
- Singular: `page` (there is no `pages`), `style`, `pregion`, `cregion`, `dregion`, `forms`, `form_data`. A plural name is a failed query, so the "Query failed." screen.

Related: docs/memory/release/how-to-add-migration.md, docs/memory/data/sql-silent-wrong-results.md, docs/memory/data/table-engines-and-row-size.md
