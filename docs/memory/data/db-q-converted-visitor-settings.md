---
name: db-q-converted-visitor-settings
description: Visitor text, retention and partition functions, InnoDB checks, output, comment counters, settings and widgets 8 and 23 on db_q(): PR, special cases, what stayed raw, test.
---

## Which files are converted, and what stayed raw?
- `includes/fn/visitor_texts.php` (PR #2349): `pg_visitor_text_id()` binds the kind bare (`(int)`) and the md5 inside `UNHEX(?)` (quoted, as before); the lookup `$find` runs twice with the same values. The sweep's DELETE keeps its integer IN list raw.
- `includes/fn/visitor_retention.php`: the chunked visitor DELETE (window ids and boundary bare) and the 14-day hour read (date quoted). `pg_visitor_move_query($sql)` stays a raw runner of the move's built statements.
- `includes/fn/visitor_partitions.php` and `includes/fn/innodb.php`: the information_schema reads bind the table name (`TABLE_NAME = ?`); innodb's restore of the session binds `lock_wait_timeout` bare and `sql_mode` quoted. Raw stay `ALTER TABLE` and the partitioned table's `MIN()`, which name a table.
- `includes/fn/comments.php`: `pg_comment_refresh_record_info()` builds `$pair` / `$published` with `?` and value arrays beside them; `_pg_comment_db_int($sql, $params)` passes them to `db_q()`. Failures still log and answer false.
- `includes/fn/output.php`: the page row lookup by id. `includes/settings/prep.php`: the live automatic bans (`expires_at > ?` bare). `includes/settings/firewall.save.php`: the manual IP list INSERT, address and list quoted, time bare. `includes/api/maintenance.php` has no query with values.
- Dashboard: widget 23's four `perf_stats` reads bind the day boundary bare; widget 8's shipped check binds the order id quoted.

## What does the guard test check?
- `tests/db_q_visitor_output_settings_test.php`: no escaped or interpolated value in a `db_q()` SQL or a raw query, no `mysqli_real_escape_string` left in innodb.php and visitor_partitions.php, one value per `?` in each literal list, and the bound text of the visitor text lookup, the retention delete, the comment counters, a firewall entry and the restored sql_mode.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/visitors/README.md, docs/memory/waf/README.md
