---
name: visitor-rollup-backfill-runtime
description: Backfill runtime rules for visitor rollups: MySQL time zone sync, interruptible steps split across versions, ceiling read order, rollup_ready recheck.
---

## Why must the backfill set its own time zone?
- The live counter picks the bucket with PHP time (`date('Y-m-d')` / `date('G')`); backfill uses MySQL time (`DATE(FROM_UNIXTIME(...))`). If they differ, old and new data land in different hours and a seam of that size appears where they meet.
- Normal requests are fine: `init.php` runs `SET time_zone = date('P')` on every request, right after setting PHP's zone. BUT `install/index.php` never loads `init.php` (only `config.php` and `functions.php`), so an upgrade runs with the MySQL server's default zone. Hence `pg_visitor_backfill_step()` first calls `pg_sync_mysql_timezone()`.
- Write the OFFSET (`date('P')`), not a name: named zones need MySQL's timezone tables, often empty on shared hosting.
- Hours that look shifted are usually the site's own time zone setting (Ayarlar -> Saat Dilimi), not code. If the shift is consistent on all screens look there first; only if it is on report screens alone look at code.

## Why must the backfill be interruptible?
- `ini_set('max_execution_time')` is not enough: IIS FastCGI (`requestTimeout` default 90 s) and nginx proxies cut the request regardless of PHP. The version number is written as soon as the upgrade function returns (`includes/migrations/runner.php`, `install_run_upgrades()`) and every step is re-runnable: an interrupted upgrade resumes, but the interrupted step restarts from scratch.
- So expensive steps are split into separate versions (3.4 schema / 3.5 backfill / 3.6 engine), each marking its own version.
- `pg_visitor_backfill_step()` works on a time budget + `config.visitor_rollup_cursor` and writes the position AFTER EVERY chunk. The rest finishes in 3-second slices as admins open the panel; nobody waits on a long page.

## In which order are the ceiling and the ready flag read?
- Read the ceiling `MAX(id)` BEFORE creating the tables. `pg_visitor_rollup_ready()` returns `true` as soon as the tables are visible and live counting starts. Ceiling read first means the two ranges cannot overlap; the few views in between are under-counted, not duplicated. If it must be wrong, wrong in that direction.
- `pg_visitor_rollup_ready($recheck)` bypasses the static cache. The upgrade creates the table within the same request; had `false` been cached, backfill would silently skip itself and report success.

Related: docs/memory/visitors/visitor-rollup-consistency.md
