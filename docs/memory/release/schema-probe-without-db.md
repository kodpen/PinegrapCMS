---
name: schema-probe-without-db
description: waf_table_has_column() with no db class loaded answers false, so pure functions that probe a column run in unit tests alone
---

## What does waf_table_has_column() do when no db class is loaded?
- It answers false (and caches it): `class_exists('db', false)` is checked before `isset(db::$con)`. `isset()` on a static property of a missing class throws `Class "db" not found`, it does not return false.
- Why: pure functions such as `erp_manual_lines_build()` (`includes/erp/invoice_manual.php`) probe a column. A filtered unit run (`php tools/test.php erp_invoice_manual`, `erp_edoc_taxpayer`) loads no test that declares a `db` stub, and the first test that reached the probe was an ERROR; the full suite passed (#4699, PR #4906). Same kind as #4354.
- `tests/waf_table_has_column_test.php` runs the probe in a PHP process of its own, where no stub exists.
