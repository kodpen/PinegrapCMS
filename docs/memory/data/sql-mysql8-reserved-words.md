---
name: sql-mysql8-reserved-words
description: A column or table alias that MySQL 8 reserves (stored, rank, row, groups…) runs on MariaDB and is a syntax error on MySQL 8; the guard test, the two Workspace queries it broke.
---

## Which words may not be an alias?
- A bare word MySQL 5.7 or 8.0 reserved: `stored`, `virtual`, `generated`, `rank`, `row`, `rows`, `row_number`, `groups`, `grouping`, `window`, `over`, `of`, `system`, `function`, `lead`, `lag`, `lateral`, `recursive`, `empty`, `except`, `member`, `array` and the other window-function names. Pick a longer name (`stored_name`, `item_rank`) rather than a backtick-quoted one.

## Why does a sandbox not show it?
- MariaDB takes most of these words as names, and `tools/setup_sandbox.sh` installs MariaDB. CI's unit tests have no database, and its install check (`install-check.yml`, MySQL 8.0 and 8.4 among others) runs the installer and the upgrade, not the screens' queries. MySQL 8 answers error 1064, `db()` logs `Pinegrap: query failed:` and the screen says "Query failed.". dev.pinegrap.com and the owner's Windows sandbox (`workflow/sandbox-on-windows.md`) run MySQL 8.0, so a query is tried there.

## What keeps it out?
- `tests/sql_reserved_alias_test.php` reads every product PHP file (libraries left out) for a capital `AS` before a bare word of its list and names the file and line. SQL in the product writes `AS` in capitals; comments write "as" in lower case and are not read as SQL. A new reserved word goes into the test's list.

## Which queries did it break?
- `f.name AS stored` in `ws_mail_pane()` (`includes/workspace/mail_pane.php`): on MySQL 8 the reading pane of the E-mails screen answered "Query failed." for every conversation and closed. The same alias in `ws_guest_file_send()` (`includes/workspace/guests.php`) failed a guest's file from their room. Both read `stored_name` now.
