---
name: visitor-disk-unknown
description: How the visitor table split and rebuild go ahead when PHP cannot read the database disk's free space: the rows' room estimate, the 5 million row bound, the stop after a failed blind try (#9396)
module: visitor_retention
---

## Why does this exist?
- Owner report 2026-11-13: on many sites the visitor table never got smaller. Where PHP cannot read the free space (`disk_free_space` off, data directory outside `open_basedir`, database on another host; most shared hosting) the split and the rebuild waited forever with reason `disk_unknown`.

## What did a sandbox of the report show? (v2026.4.10, MariaDB 10.11, 5 M visits)
- No table that no option made smaller: in every state one button did ("Shrink now" 784 to 664 MB; a shorter period, then a dropped month, 949 to 785 MB).
- A table not split: deleting rows never changes the file; only the split or a rebuild gives the room back.
- Every visit inside the kept period: "Clean up visitor records now" has nothing to do; the file follows a shorter period only when a whole month goes or by "Shrink now". The card says so since #9418 (`visitor-records-card-texts.md`).
- While the rollup backfill is behind, a run removes only what it passed (20,000 to 50,000 rows).

## How big are the rows left?
- `pg_visitor_rows_room_estimate()`: the smaller of the statistics (data + index; MyISAM less its free) and records × `PG_VISITOR_ROW_BYTES` (384 B).
- After a large delete InnoDB still counts the emptied pages: sandbox, 5 M left of 10 M, statistics 2.9 GB, rebuilt file 1.6 GB. Measured 350–367 B per row with referrer and user agent on the row.
- The room asked for uses it: move 1.5 × rows + 256 MB, rebuild rows + min(index, rows) + 256 MB (was 3 × + 1 GB).
- A rebuild is also worth it when the rows take at most half the file and give ≥ 128 MB back (`pg_visitor_shrink_worth_by_file()`).

## When does it go ahead blind?
- `pg_visitor_blind_ok()`: rows' room ≤ half the file, ≤ 5,000,000 records, ≤ 2 GB. The copy needs only the rows' room, and the whole old file comes back after. The other holds stay (backlog, quiet hour, week after a rebuild).
- Sandbox (MariaDB 10.11): 5 M rows in a 3.4 GB file rebuilt blind in 29 s to 1.6 GB; 1 M rows in a 1.6 GB file split blind in three runs of ≤ 15.1 s to 376 MB, no visit lost.

## What after a failed blind try?
- The marker (`visitor_move.json`, `visitor_shrink.json`) keeps `blind` (the try started without the disk read) and `blind_failed` (`pg_visitor_blind_failed_after()`, applied on every save).
- After the week's `failed` hold the reason is `blind_failed`; the card says the general job does not try that way again and the button still runs it. A finished move or rebuild clears it.
- Tried by making the rebuild fail (`max_statement_time` 2 s): state failed, `blind_failed` true; "Shrink now" then rebuilt it and cleared it.

Related: docs/memory/visitors/visitor-retention-and-cleanup.md, docs/memory/visitors/visitor-table-size-figures.md
