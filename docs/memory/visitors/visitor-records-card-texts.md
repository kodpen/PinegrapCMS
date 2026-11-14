---
name: visitor-records-card-texts
description: What the Visitor records card says and offers (#9418): month names, why Clean up is off, every record inside the period, a rebuild that gave nothing back, Shrink now
---

## How are the months named?
- "Monthly partitions" names each month by its partition's name (`p202609` → 2026-09, `pg_visitor_partition_month_label($bound, $name)`); without a month name, from a day before the bound.
- Before #9418 it used the bound's last second on today's PHP clock: a table split while PHP ran on UTC read one month too late on a UTC+3 site (owner report 2026-11-13).

## Why is "Clean up visitor records now" off?
- `pg_visitor_cleanup_disabled_reason()`: `busy` (another job of the screen runs), `no_period` (kept without a limit), `inside_period` (nothing older than the period). The sentence above the button says which.
- Every record inside the period: `pg_visitor_inside_period_text()` says nothing is to clean and the table stays this size, and what a shorter period does: whole months go at once (split table), or the room comes back at the rebuild (InnoDB) or the move (MyISAM).

## When does it say the file was made smaller?
- Only when `pg_visitor_shrink_gave_room()`: at least 1 MB and 1 % smaller. Otherwise "rebuilt; its file stayed about the same size", in the card and the activity log.
- While a split runs, the rebuild's `moving` reason says "being split into one partition per month", not "moved to InnoDB".

## When is "Shrink now" offered?
- Only when `pg_visitor_shrink_offered()`: `pg_visitor_shrink_gain()` ≥ 32 MB, the larger of the server's free space and the file less the rows' room (`visitor-disk-unknown.md`). The note under the buttons names it only then.

Related: docs/memory/visitors/visitor-retention-and-cleanup.md, docs/memory/visitors/visitor-disk-unknown.md
