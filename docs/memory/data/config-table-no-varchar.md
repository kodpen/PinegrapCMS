---
name: config-table-no-varchar
description: The config table takes no new column since 11.0.145 (before that no VARCHAR, TEXT only): the InnoDB row-size reasons, the test that enforces it, config_text for new settings.
module: config_text
---

## May a column be added to the `config` table?
- No, since 11.0.145 (#5227): the stock row is 1 byte under the room `tests/innodb_test.php` keeps on MySQL 8.0, so a TINYINT breaks the test as much as a TEXT would. A new setting, a short flag included, goes to `config_text` or a table of its own.
- Before that, since 2026.4.8 (8.17): never a new VARCHAR, TEXT only (`TEXT DEFAULT NULL`, the `waf_exclusions` pattern); the 124 VARCHAR columns were converted to TEXT, and short fixed columns (TINYINT/INT/ENUM) were fine while the room lasted.

## Why?
- The row's VARCHAR columns take about 63 KB in utf8mb4, leaving a few hundred bytes to InnoDB's 65535-byte row limit. `erp_overdue_notify_recipients VARCHAR(500)` failed on upgrade with 1118 (Row size too large) and became TEXT.
- Two limits apply: the server's 65,535 and InnoDB's 8126; VARCHAR up to 255 octets cannot go off-row (MySQL 8.0 rejected the stock table at 8468).

## Where does a new setting go?
- Into `config_text` (name VARCHAR(64) key, value TEXT; 2026.11.0, 11.0.48) through `pg_config_text($name)` / `pg_config_text_save($name, $value)` (`includes/fn/config_text.php`, tests `tests/config_text_test.php`), not a new `config` column: from 11.0.49 the row had less than the ten long columns of room the test keeps, from 11.0.145 one byte (#5227). Several short settings may share one JSON row. The first row is `ecommerce_kvkk_consent_text` (commerce/checkout-kvkk-notice.md).

## What enforces it?
- `tests/innodb_test.php` (`test_innodb_stock_config_row_fits`) turns red for any migration that adds a column to `config`; before 11.0.145 only a VARCHAR did.
- Consequences elsewhere: no `config` column was opened for the waybill series (`ERP_WAYBILL_SERIES` is a constant), e-document credentials are NOT written to `config` (they use `erp_edoc_providers`), and the walk-in account id is an INT UNSIGNED.

Related: docs/memory/erp/erp-overdue-reminders.md
