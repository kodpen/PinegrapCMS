---
name: table-engines-and-row-size
description: Table engines since 2026.4.8 (InnoDB, MyISAM left over the limits), why a write over several tables validates first, InnoDB's 8126-byte row rule, pg_innodb_row_estimate(), maintenance code.
---

## Which engine is a table on?
- InnoDB for the tables the software creates: 2026.4.8 steps 8.10-8.14 convert the groups of `pg_innodb_table_groups()` (`includes/fn/innodb.php`) through `pg_innodb_convert_table()`; `visitors`, `user` and `files` moved earlier (2026.3.6, 2026.4.4). `tests/innodb_test.php` compares that list with the starter dumps and the migrations, so a forgotten table fails the test.
- The upgrade leaves a table on MyISAM when it is over 250,000 rows or 128 MB (`upgrade_2026_4_8_innodb_limits()`: no upgrade request copies a table that big), when its widest row does not fit InnoDB (state `too_wide`), or when an earlier attempt did not finish. The operator moves it from the Database Engine screen (`database_engine.php`) when the site is quiet. So on a given site any table may still be MyISAM.

## What does that mean for a write over several tables?
- MyISAM has no transactions. Validate everything first, then write, in an order that leaves nothing broken if the request dies half way (`ui/offer-editor.md`, `designer/multi-page-designer-editor.md`).
- A transaction does not cover a MyISAM table: a change to one survives the rollback. That is why ERP moves stock after its document commits (`erp/erp-stock-moves-and-cost.md`).

## How wide may a row be on InnoDB?
- 8126 bytes on the default 16 KB page (`pg_innodb_row_limit()`), next to the server's 65,535-byte limit for any engine.
- A TEXT/BLOB column, or a VARCHAR whose longest value is over 255 bytes, can leave the page and costs 41 bytes in the row on MySQL 8.0 (21 on MariaDB 10.4+). A VARCHAR of 255 bytes or less never leaves the row and counts at full length plus one: in utf8mb4 that is VARCHAR(63) and below. MySQL 5.7 counts more leniently and fails later, on write.
- So on a table with many columns open TEXT or VARCHAR(64) and up, not many short VARCHARs. Over the limit the server refuses the CREATE or ALTER with error 1118 (with `innodb_strict_mode` off it fails on write instead).
- Estimate with `pg_innodb_row_estimate($table)`: counts the way this server does (`pg_innodb_server_rule()`) and answers `bytes`, `limit`, `fits`. The `config` table gets no VARCHAR at all: `config-table-no-varchar.md`.

## Where is table maintenance?
- `includes/fn/db_maintenance.php`: the table catalog (`pg_db_table_catalog()`), the server's facts and version check (`pg_db_server_facts()`, `pg_db_server_supported()`), OPTIMIZE / CHECK / REPAIR TABLE on one table, and the findings the Database Engine screen lists. It and `innodb.php` call `mysqli_query()` directly: a failure there is one of the answers, not the end of the request.

Related: docs/memory/data/db-helpers-and-error-contract.md, docs/memory/release/database-server-compatibility.md
