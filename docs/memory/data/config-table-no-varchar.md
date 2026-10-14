---
name: config-table-no-varchar
description: Rule that the config table gets no new VARCHAR columns (TEXT only), with the InnoDB row-size reasons and the test that enforces it.
---

## Which column type may be added to the `config` table?
- Never a new VARCHAR; add TEXT (`TEXT DEFAULT NULL`, the `waf_exclusions` pattern). Short fixed columns (TINYINT/INT/ENUM) are fine.
- Since 2026.4.8 (8.17) the table has no VARCHAR at all; the existing 124 columns were converted to TEXT.

## Why?
- The row's VARCHAR columns take about 63 KB in utf8mb4, leaving a few hundred bytes to InnoDB's 65535-byte row limit. `erp_overdue_notify_recipients VARCHAR(500)` failed on upgrade with 1118 (Row size too large) and became TEXT.
- Two limits apply: the server's 65,535 and InnoDB's 8126; VARCHAR up to 255 octets cannot go off-row (MySQL 8.0 rejected the stock table at 8468).

## What enforces it?
- `tests/innodb_test.php` turns red for any migration that adds a VARCHAR to `config`.
- Consequences elsewhere: no `config` column was opened for the waybill series (`ERP_WAYBILL_SERIES` is a constant), e-document credentials are NOT written to `config` (they use `erp_edoc_providers`), and the walk-in account id is an INT UNSIGNED.

Related: docs/memory/erp/erp-overdue-reminders.md
