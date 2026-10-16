---
name: visitor-monthly-partitions
description: The visitors table split into one partition per month (step 10.7, #499): how months are dropped, opened, converted, and what happens without partitioning
---

## Why does this exist?
- Symptom: on a big table the 90-day cleanup deleted for hours in 5,000-row chunks, and the room stayed in the `.ibd` file until "Shrink now" rebuilt the whole table (incident 2026-10-14, 950 MB backup). Plan item 2 of docs/memory/plans/visitor-records-deferred.md.
- With one partition per month a whole old month goes with `DROP PARTITION` in a moment and its file goes back to the disk. Code: `includes/fn/visitor_partitions.php` (#499).

## What does the table look like?
- `PARTITION BY RANGE (start_timestamp)`, one partition per month of the site's PHP clock, named `p` + Ym (`p202610`), bound = first second of the next month, last `pmax VALUES LESS THAN MAXVALUE` so visits are recorded even if no month is opened.
- Primary key `(id, start_timestamp)`: every unique key must hold the partition column. `id` stays first, so AUTO_INCREMENT and `WHERE id = …` updates work unchanged. Only InnoDB is split.
- The plan runs from the oldest row's month (at most 36 months back) to two months ahead; older rows and rows with `start_timestamp = 0` sit in the first partition.

## Who drops and opens months?
- `pg_visitor_cleanup_run()`: before the chunks it drops every month whose bound ≤ the delete boundary (never the last month, never `pmax`), counting its rows first; the rest of the boundary month goes in chunks. Not while the hourly rollup backfill is behind. Result text adds "N whole month(s) went at once".
- `job.php` daily (hourly while waiting/failed): `pg_visitor_partition_open_run()` turns `pmax` into the missing months + `pmax` with `REORGANIZE PARTITION`, only when `pmax` holds ≤ 50,000 rows, no long statement runs, lock wait 2 s. Marker `data/temp/visitor_partitions.json`.
- Shrink: a split table is not rebuilt automatically (hold reason `partitioned`); "Shrink now" still works (tested on all four servers).

## How does a table get split?
- Step 10.7 (2026.4.10): InnoDB with ≤ 250,000 rows → one `ALTER` (20 s lock wait). Larger tables and MyISAM are left to the general job (states `large`, `engine`; #489 pattern).
- The #426 copy-and-rename move does the rest: kind `split` for unsplit InnoDB, kind `engine` for MyISAM (split on the way). "Split into months now" on the card asks for it. The move's final check now joins on `id` and `start_timestamp`.
- File size on the card sums `visitors#P#*.ibd` / `#p#` files or `SUM(FILE_SIZE)` of the server's tablespaces.

## What if the server has no partitioning?
- Support: MySQL 8 always; MySQL 5.7 / MariaDB need the `partition` plugin ACTIVE. Otherwise the step reports why and leaves the table; cleanup keeps deleting in chunks. MySQL 5.7 with `--skip-partition` still runs InnoDB partitions, the check stays cautious there.
- Restore: `visitors` is dumped without rows; a CREATE with a partition clause that fails is retried without it (`pg_restore_without_partitions()`, both `/*!50100 PARTITION BY … */` and MariaDB's plain form). A restored old layout gets its missing months from the daily job.

## What to expect?
- Each month's file keeps a few MB of reserved room, so a small or medium table can be larger on disk after the split (320k test rows: 64 MB → 136 MB). The gain is that a dropped month gives all its room back.

Related: docs/memory/visitors/visitor-retention-and-cleanup.md, docs/memory/visitors/visitor-table-size-figures.md
