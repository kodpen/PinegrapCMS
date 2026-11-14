---
name: visitor-retention-and-cleanup
description: How the visitors table is kept small: retention days, daily summary, chunked deletion, monthly partitions, move to InnoDB, automatic rebuild, bots not recorded, rowless backups, stepwise restore
---

## Why does this exist?
- One `visitors` row per visit, never deleted: 3.8 million rows, over 800 MB on busy sites. A big `DELETE` locks the table for minutes; on MyISAM every visit waits. A client's 950 MB backup could not be restored because of it (incident 2026-10-14).
- Code: `includes/fn/visitor_retention.php`; settings: Visitors and privacy › Visitor tracking; card: Database engine › Visitor records. Schema step 10.3.

## What are the rules and defaults?
- `config.visitor_retention_days`: default 90, 0 = keep forever, allowed 7–3650 (invalid input is refused with a field error). The column was added with `DEFAULT 90`, so upgraded sites get 90; 0 only where someone chose it, and no step overwrites that choice.
- `config.visitor_summary` (default on): every day through yesterday is summarised into `visitor_stats_daily`, whatever the retention; only summarised days are deleted. Day order, `pg_visitor_rows_from()` and how reports read it: `visitor-daily-summary.md`.
- Warning when the table passes 2,000,000 rows or a 512 MB file (`pg_visitor_health_thresholds()`), on the card and in System status (no score effect). Figure sources: `visitor-table-size-figures`.

## How does deletion run?
- In `job.php` (`pg_visitor_cleanup_run()`): every tick while there is a backlog, else hourly, also with retention off while the summary is on; 15 s per run, the summary takes at most ~60 %.
- Primary-key windows (`id > a AND id <= b AND start_timestamp < cutoff`): InnoDB 5,000 rows with 20 ms pauses, MyISAM 1,000 rows with a pause as long as the chunk took. `ORDER BY id LIMIT` was tried first and was slow (the server picked the timestamp index). `GET_LOCK` keeps runs apart.
- "Clean visitor records now" on the card runs 20 s steps while the page is open. "Optimize" is separate and warned (free disk = table size).
- Upgrades never delete rows.

## What else keeps it small?
- One partition per month (step 10.7, #499): old months are dropped at once (`visitor-monthly-partitions`).
- MyISAM `visitors` moves to InnoDB by itself (`pg_visitor_move_run()`, #426; split into months on the way) once the backlog is gone and the local DB disk has 1.5 × the rows' room + 256 MB free (`pg_visitor_move_disk_needed()`, #9396): chunked copy, catch-up of changed rows, `RENAME TABLE`. "Move to InnoDB now" skips the disk and backlog checks.
- InnoDB file rebuilt online (`pg_visitor_shrink_*`, #453) when free space ≥ 128 MB and more than half of the remaining rows, or the rows' room is at most half the file and gives ≥ 128 MB back (#9396), in the quietest hour of the last two weeks, with rows' room + min(index, rows' room) + 256 MB free; otherwise "Shrink now" with the missing condition.

- Free disk space unreadable: `visitor-disk-unknown.md`.

## What do backups and restores do with it?
- Every backup dumps the tables in `pg_backup_rowless_tables()` (visitors, form view tables, logs; #434, #438, #480; docs/memory/data/backup-rowless-tables.md) as structure only; the move's working copies are never dumped; the size estimate skips them too.
- Big `.sql` and `.zip` backups restore in 20 s steps into `pgr_new_` tables, then one `RENAME TABLE` (`includes/fn/backup_restore.php`, #441, #451); a half-done restore can be resumed or deleted.

Related: docs/memory/platform/backup-upload-extraction.md
