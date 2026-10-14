---
name: erp-aging-and-due-date
description: Single source for ERP due dates and aging (includes/erp/aging.php): point-in-time open amounts, buckets, filters, and payment_days default due dates.
---

## Where does aging and overdue logic live?
`includes/erp/aging.php` is the single source; a new due view is fed from here and writes no query of its own.
- `erp_aging_invoices($direction, $date, $filters)` returns invoices open on that date: point in time, so settlements/returns after the date are not deducted and later-issued documents are absent.
- `open_base` comes from the recorded-rate `*_base` columns: `grand_total_base − Σ amount_base − Σ return.grand_total_base`, below 0 → 0; no new rounding.
- Buckets by `erp_aging_bucket()`: ≤0 / 1-30 / 31-60 / 61-90 / 90+.
- `due_date = 0000-00-00` counts as the invoice date (`erp_aging_due_sql()`).
- Readers of these functions: report `erp_aging.php` (`?csv=1` same table, no log), list badges, filters `?filter=overdue|due_week|open` plus `direction` / `account_id` / `bucket` / `as_of`, and dashboard cards.
- The report reads only `due_date`.

## How is the due date defaulted? (4.53)
- Sources: `erp_accounts.payment_days` and `config.erp_default_due_days` / `ERP_DEFAULT_DUE_DAYS`.
- One helper, `erp_account_due_date($account_id, $issue_date)` (`accounts.php`): account > store > invoice date.
- Read once when the document is written (`order_bridge`, `erp_manual_header_build()`, and a due left blank in the invoice header form); the written date is never overwritten, a return keeps the invoice date.
- CSV account import/export carries the field with the label "Vade (Gün)" (after Notlar).

Related: docs/memory/erp/erp-account-csv-import-export.md
