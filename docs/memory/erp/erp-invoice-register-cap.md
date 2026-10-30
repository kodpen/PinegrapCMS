---
name: erp-invoice-register-cap
description: The ERP invoice register reads at most 500 rows; a full list is counted over the same conditions and the note and a line under the table name both numbers
---

## How many invoices does the register show?
- `erp_invoices.php` reads at most `$invoice_limit` (500) rows, newest first, for every view (due-date, aging bucket, account, e-document). The cap is kept.

## How does it say the list was cut?
- When the list comes back full, `SELECT COUNT(*) FROM erp_invoices i` runs over the same `$where` (the conditions use only the `i` alias, so no join is needed). The filter note then reads `'{var:1} of {var:2}'` ("500 of 1,240", `pg_format_number()`), and a `form-text` line under the table says only the latest rows of how many are shown and how to narrow the list, as `erp_quotes.php` does for its 300.
- Before PR #3739, the note printed `count($invoices)`, never above 500, while the e-document badges (`erp_edoc_filter_counts()`) and the dashboard links that open the list count the whole register: a badge said 1,240 and the list said 500, with no word about the rest.

## Where is it tested?
- `tests/erp_invoice_register_count_test.php` pins the shared cap, the count over `$where`, the note, the line under the table and their Turkish values.

Related: docs/memory/erp/erp-quotes.md, docs/memory/ui/list-and-table-screens.md
