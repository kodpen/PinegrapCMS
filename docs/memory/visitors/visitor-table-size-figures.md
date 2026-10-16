---
name: visitor-table-size-figures
description: Where the visitor card, the System Status line and the shrink/move before-after sizes get their record count and file size, and how each figure says its source
---

## Why not just information_schema.TABLES?
- On InnoDB `TABLE_ROWS`, `DATA_LENGTH` and `INDEX_LENGTH` come from a page sample refreshed after a tenth of the rows change. Sandbox: right after inserting 900k rows they said 320 KB for a 180 MB file; right after `ENGINE=InnoDB` 64 KB for 84 MB; after a big delete data + index + free said 593 MB for a 444 MB file. The card, the System Status warning and the "Shrink now" before/after text were all built on these (#453 left this open).

## Where does each figure come from now? (PR #479)
- `pg_visitor_table_figures($catalog_row)` in `includes/fn/visitor_retention.php`, used by the Database Engine card and System Status.
- Records: MyISAM's exact count; InnoDB `COUNT(*)` while the estimate is at most `PG_VISITOR_COUNT_MAX_ROWS` (1,000,000; ~0.1–0.2 s at 900k in the sandbox), above that the estimate, shown as "about N".
- File size: `pg_visitor_table_file_size()` tries in order:
  1. `file` — `filesize()` of `@@datadir/<db>/visitors.ibd` (MyISAM `.MYD` + `.MYI`), only when `DB_HOST` is local (`pg_visitor_move_db_is_local()`); folder name in MySQL's file-name spelling (`pg_visitor_db_file_name()`, `-` → `@002d`, non-ASCII → skipped), also tried in lower case. `clearstatcache()` first, the rebuild reads it twice in one request. Usually fails on Linux (datadir 0700 mysql) and may fail on Windows/IIS; that is expected.
  2. `server` — InnoDB `FILE_SIZE` from `information_schema.INNODB_TABLESPACES` (MySQL 8) or `INNODB_SYS_TABLESPACES` (5.7, MariaDB); needs PROCESS. MyISAM statistics are already the file sizes.
  3. `estimate` — data + index + free; `shared` when `innodb_file_per_table` is off.
- Free space: `DATA_FREE` as the server reports it (whole free extents on InnoDB), unchanged.
- The card prints each source after the value ("counted", "read from the file", "reported by the database server", "estimate from the server statistics"); estimates get "about".

## What else uses it?
- `pg_visitor_table_is_large($figures)`: 2M records or a 512 MB file. A cleaned table whose file is still big stays flagged until it is rebuilt; the card's warning then says most of the file is free space.
- Shrink marker `data/temp/visitor_shrink.json`: `file_before`/`file_after` from the file reader plus `file_source`; the "rebuilt: X before, Y now" texts add "about" when it was an estimate. The 0.9 check for a rebuild whose request never came back uses the same reader. Sandbox: 444 MB → 84 MB, both read from the file.
- Move marker: `bytes_before`/`bytes_after` (+ `bytes_source`) are file sizes too.
- System Status is cached 10 min, so the COUNT runs at most then.
