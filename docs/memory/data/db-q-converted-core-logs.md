---
name: db-q-converted-core-logs
description: Price history, core callers, calendar, mail, not-found log, db maintenance and backup restore functions converted to db_q(): PR, special cases, what stayed raw, guard test.
---

## Which files are converted, and what stayed raw?
- `includes/fn/not_found_log.php` (PR #2321): 5 calls. The counter UPDATE binds its stale check (`$stale = "last_seen < ?"`, used four times) with the value repeated in order; the crawler flag stays a bare number (`(int)`), the times and the key are quoted. `mysqli_affected_rows()` after `db_q()` still reads the same connection. The cap's `LIMIT 1 OFFSET` constant and the `MAX(id)` read stay raw.
- `includes/fn/price_history.php`: the product delete and the offer pass walk (`id > ?` quoted, `LIMIT ?` bare). Raw stay the SQL from the pure builders (`pg_price_history_*_sql()`), the integer IN list and the base currency read.
- `includes/fn/core.php`: the callers only, the online timestamp updates (`$ct` bare) and `create_notification()` (ten quoted values; a missing id stays `''`). The connection setup (`SELECT 1`, `sql_mode`, `SET NAMES`) and `pg_db_run()` / `db_q()` themselves stay raw.
- `includes/fn/calendar.php`: 4 calls (event lookup, remaining spots, locations, calendars of an event). Raw stay `$sql_where` of the event list and the room check completed by `$event_id_where_clause`, whose value is written in double quotes: binding it would change the text.
- `includes/fn/mail.php`: 7 calls of the recipient list and the comment e-mails. The byte pin of the two comment e-mail functions in `tests/comments_widget_email_test.php` was updated with this change.
- `includes/fn/db_maintenance.php`: the processlist LIKE list (`?` with `verb%` as the value) and the two `lock_wait_timeout` sets (bare ints). Raw stay the statement with a table name and the reads without values.
- `includes/fn/backup_restore.php`: `SHOW TABLES LIKE ?`, `GET_LOCK(?, 0)` and the session restore, where a string value is bound and a digit-only value stays bare (the switches refuse a quoted '1'). Raw stay the dump statements, `DROP` / `RENAME` with table names and `SHOW TABLES`.

## What does the guard test check?
- `tests/db_q_core_logs_backup_test.php`: no escaped value in a `db_q()` SQL or a raw query (clauses listed by name), no `mysqli_real_escape_string` left in the three log and backup files, one value per `?` in each literal list, and the bound text of the not-found counter, the LIKE list, the bare numbers and a notification.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-converted-access.md
