---
name: csv-formula-guard
description: pg_csv_formula_guard() puts an apostrophe before a leading = + - @ tab or CR in exported text cells (orders exports included); pg_csv_formula_unguard() takes it off on import
---

## Which cells get the formula guard?
- `pg_csv_formula_guard()` (`includes/fn/core.php`) puts `'` before a leading `= + - @` tab or CR; `csv_cell()` and `pg_audit_view_csv_line()` use it. The ERP audit trail (user, action, number or name, details) and aging (account title) CSVs use it since PR #3682: a web order's billing name `=1+1` ran in Excel. Never on amounts (`-12.50`), dates, ids, or in `erp_export_write_csv()`.
- Orders exports (`view_orders.php`, single file and zip `orders.csv` / `ship_tos.csv`): `pg_csv_formula_guard()` at the output line on the shopper- and visitor-typed cells (billing and ship-to names, company, address, city, state, zip, phone, fax, e-mail, cardholder, PO number, custom fields 1/2, referral/offer/tracking/affiliate codes, member id, `utm_*`, `http_referer`); custom billing, custom shipping and product form answers through `csv_cell()`. A billing name `=1+1` ran when the operator opened the file. The shared per-row quote loops stay unguarded: they run before card decryption (base64 can start with `+`) and the `/ 100` money columns. Ids, order number, card, money, dates, transaction codes, currency, IP and tracking numbers (read back by `import_shipments.php`) stay as they are.
- Tests: `tests/csv_export_test.php`, `tests/csv_line_endings_test.php`.

## Does an import take the guard off?
- `pg_csv_formula_unguard()` (beside the guard) drops a leading `'` only when `= + - @` tab or CR follows: the exact inverse. Import Submitted Forms runs it on every non-file cell (before the multi-value split) and the short link reader on every cell. Before, importing an unchanged submitted forms export stored `'+90 532…`, `'@pinegrap`, `'-5`, shown on the form screen and sent in e-mails.
- An answer typed as `'+1` loses its apostrophe on import too; the export cannot tell the two apart.
