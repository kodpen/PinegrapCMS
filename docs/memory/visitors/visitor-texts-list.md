---
name: visitor-texts-list
description: Referring address and user agent of a visit kept once each in visitor_texts and named by number in visitors (step 10.6): writer, readers, prune, old rows, upgrade limits
---

## What changed and why? (PR #489)
- `visitors.http_referer` and `visitors.user_agent` (VARCHAR 255) were most of every row; a site sees the same few hundred browsers and the same origins again and again (browsers send only the origin across sites since 2020).
- Since 2026.4.10 step 10.6 a new visit stores `http_referer_id` / `user_agent_id` and leaves both text columns `''`; the texts live once in `visitor_texts` (`text_id`, `kind` 1 referer / 2 user agent, `text_hash` = `UNHEX(MD5(text))` under `uk_text (kind, text_hash)`, `text_value`, `last_used`).
- Sandbox, 300,000 rows, InnoDB: average row 261 → 114 bytes, data 73.6 → 32.6 MB; the list held 455 values (0.1 MB). With 10% of visits on unique full addresses the list held ~40,000 values and the table gain was ~35%.
- Code: `includes/fn/visitor_texts.php`. The other long columns (`utm_term`, `utm_content`, `referring_search_terms`, `site_search_terms`) stay: almost always empty (1 byte), and UTM tags and site search terms are updated later in the visit from several files.

## How is a visit written?
- `init_tracking()` in `get_page.php`, only when `pg_visitor_texts_ready()` (table + both columns): `pg_visitor_text_id()` per text. Text cleaned first (`pg_visitor_text_clean()`: valid UTF-8, 255 characters), hash taken in PHP. Hash is binary, so "Mozilla" and "mozilla" are two values (the column collation is case-insensitive).
- Lookup: SELECT by hash; `last_used` refreshed only when older than a day (`PG_VISITOR_TEXT_REFRESH_SECONDS`), so one UPDATE a day per value. An UPDATE that touches no row means the prune took it: the value is inserted again. Miss: `INSERT IGNORE`, then re-read on a race. Empty text = 0, no row.
- If the list cannot be read or written (`false`), the visit keeps its texts in the row as before.

## How do readers handle old and new rows?
- `pg_visitor_text_sql('http_referer'|'user_agent')` = `COALESCE(vt_referer.text_value, visitors.http_referer)`; `pg_visitor_text_joins($sql)` adds the LEFT JOIN only for aliases the SQL names. Before the step both return the plain column / nothing.
- Used by: visitor report grouping (URL, Device), its URL filter, detail list and CSV (`view_visitor_report.php`), daily summary device dimension (`pg_visitor_summarize_day()`), `GET /reports/visitors?by=device`, `view_visitor.php`.
- The list's key is `text_id`, not `id`: unqualified `id` in visitor queries stays unambiguous after the join.
- Old rows are not rewritten: the retention period removes them, so a pass over the table would buy nothing. With retention 0 they stay in the old form (still read).

## When is a value removed?
- `pg_visitor_texts_prune()` at the end of `pg_visitor_cleanup_run()` (inside its lock, with its leftover time): values with `last_used` < (oldest remaining `start_timestamp`, or now) − 2 days, in `text_id` windows, re-checked in the DELETE. Safe because a visit's start is never more than a day after its values' `last_used`.
- Backups dump `visitor_texts` without rows, like `visitors` (docs/memory/data/backup-rowless-tables.md).

## What does the upgrade do on each server?
- `install_add_columns_instant()` (runner.php): `ALTER ... ADD ..., ADD ..., ALGORITHM=INSTANT`, lock wait 20 s. InnoDB on MySQL 8.0.12+ / MariaDB 10.3.2+: instant at any size.
- Refused (MyISAM, MySQL 5.7, MariaDB 10.2: MySQL 1845 / syntax error): plain `install_add_column()` when the table has ≤ 250,000 rows (bounded COUNT); otherwise nothing changes, a note says to re-run the step from Software Repair once the cleanup made it smaller, and visits keep their texts.
- Database Engine › Visitor records card: "Addresses and browsers" = number of values, or "Kept in each record" before/without the columns.

Related: visitor-retention-and-cleanup, visitor-table-size-figures, docs/memory/plans/visitor-records-deferred.md
