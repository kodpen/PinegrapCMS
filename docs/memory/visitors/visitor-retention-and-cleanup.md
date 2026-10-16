---
name: visitor-retention-and-cleanup
description: How the visitors table is kept small: retention days, daily summary, chunked deletion, move to InnoDB, automatic rebuild, bots not recorded, rowless backups, stepwise restore
---

## Why does this exist?
- Every visit wrote a `visitors` row and nothing was deleted: 3.8 million rows and over 800 MB on busy sites. One big `DELETE` locks the table for minutes; on MyISAM every visit waits ("the whole site locks"). A client's 950 MB backup could not be restored because of this table (incident 2026-10-14).
- Code: `includes/fn/visitor_retention.php`; settings: Settings › SEO › Visitor tracking; card: Database engine › Visitor records. Schema step 10.3.

## What are the rules and defaults?
- `config.visitor_retention_days`: default 90, 0 = keep forever, allowed 7–3650 (invalid input is refused with a field error). The column was added with `DEFAULT 90`, so upgraded sites get 90; 0 only where someone chose it, and no step overwrites that choice.
- `config.visitor_summary` (default on): before deleting, each day is summarised into `visitor_stats_daily` with the report's grouping keys; top 100 values per dimension plus one rest row (`is_rest`); the total row (`dimension = ''`) is written last and marks the day done. Only summarised days are deleted.
- Reports and `GET /reports/visitors` read the summary before the first raw day and raw rows after it (year/month/day + at most one grouping, no filters), answer `summary_to`, and say which days a report cannot cover.
- Warning when the table passes 2,000,000 rows or 512 MB (`pg_visitor_health_thresholds()`), on the card and in System status (no score effect).

## How does deletion run?
- In `job.php` (`pg_visitor_cleanup_run()`): every tick while there is a backlog, else hourly; 15 s per run, the summary takes at most ~60 %.
- Primary-key windows (`id > a AND id <= b AND start_timestamp < cutoff`): InnoDB 5,000 rows with 20 ms pauses, MyISAM 1,000 rows with a pause as long as the chunk took. `ORDER BY id LIMIT` was tried first and was slow (the server picked the timestamp index). `GET_LOCK` keeps runs apart.
- "Clean visitor records now" on the card runs 20 s steps while the page is open. "Optimize" is separate and warned (needs free disk equal to the table).
- Upgrades never delete rows.

## What else keeps it small?
- MyISAM `visitors` moves to InnoDB by itself (`pg_visitor_move_run()`, #426) once the backlog is gone and the local DB disk has 3 × the rows + 1 GB free: copy in 2,000-row chunks, catch up changed rows, `RENAME TABLE`; old table kept as `visitors_myisam` until checked. "Move to InnoDB now" skips the disk and backlog checks.
- InnoDB file rebuilt online (`pg_visitor_shrink_*`, #453) when free space ≥ 128 MB and more than half of the remaining rows, in the quietest hour of the last two weeks, with 3 × (data + index) + 1 GB free; otherwise "Shrink now" with the missing condition.
- Bots are not recorded even with the WAF off: `init_tracking()` asks `waf_classify_bot()`.
- On InnoDB the `ip_address` and `idx_user_agent` indexes were dropped (~350 MB on the 3.8 M sample).

## What do backups and restores do with it?
- Every backup dumps the tables in `pg_backup_rowless_tables()` (visitors, form view tables, `waf_log`, `perf_log`, `api_request_log`) as structure only (#434, #438); the move's working copies are never dumped; the size estimate skips them too.
- Big `.sql` and `.zip` backups restore in 20 s steps into `pgr_new_` tables, then one `RENAME TABLE` (`includes/fn/backup_restore.php`, #441, #451); a half-done restore can be resumed or deleted.

Related: docs/memory/platform/backup-upload-extraction.md
