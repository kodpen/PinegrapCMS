---
name: innodb-conversion-marker
description: How pg_innodb_convert_table() remembers a MyISAM-to-InnoDB conversion that died, and why an aborted table survives the next table's conversion
module: innodb
---

## What does the conversion marker hold?
- `data/temp/innodb_convert.json` (`pg_innodb_temp_marker()`): `table`, `started` and `pid` of the conversion now running, plus `earlier`: table => start time of the attempts that died and were never retried.
- `pg_innodb_marker_begin()` writes it before the ALTER and moves any table already in it to `earlier`. `pg_innodb_marker_end()` takes the table out after the ALTER, whichever way it ended, and makes the first `earlier` table the marker's `table` again. When nothing is left the file is removed.
- A table in the marker that is still MyISAM, with no ALTER running on it and a start at least 30 s old, answers `aborted`. The upgrade (`retry` false) leaves it alone; the Database Engine screen (`retry` true) tries it again and shows the marker's `table` as an unfinished attempt.

## Why `earlier`?
- The 2026.4.8 step goes on to the next table after an `aborted` one. With one entry per file, that table's conversion overwrote the record and then deleted it, so the next pass started the dead table again unattended, and the screen stopped naming it. Fixed by the bug hunt of the `innodb` module.
- Tests: `tests/innodb_test.php` `test_innodb_marker_keeps_an_earlier_aborted_table`.
