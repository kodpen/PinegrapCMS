---
name: csv-formula-guard
description: pg_csv_formula_guard() puts an apostrophe before a leading = + - @ tab or CR in exported text cells (orders, gift cards, visitor report); pg_csv_formula_unguard() undoes it on import
---

## Which cells get the formula guard?
- `pg_csv_formula_guard()` (`includes/fn/core.php`) puts `'` before a leading `= + - @` tab or CR; `csv_cell()` and `pg_audit_view_csv_line()` use it. The ERP audit trail (user, action, number or name, details) and aging (account title) CSVs use it since PR #3682: a web order's billing name `=1+1` ran in Excel. Never on amounts (`-12.50`), dates, ids, or in `erp_export_write_csv()`.
- Orders exports (`view_orders.php`, single file and the zip's `orders.csv`, `ship_tos.csv`, `order_items.csv`, `shipping_tracking_numbers.csv`): every text cell. A billing name `=1+1` ran when the operator opened the file.
- The values the shared per-row loops quoted take `pg_csv_formula_guard()` at the output line: the loops double quotes and run before card decryption (base64 can start with `+`) and the `/ 100` money columns. Form answers, form headings and tracking numbers, read raw, take `csv_cell()`.
- Guarded: names, addresses, phones, e-mail, cardholder, PO number, custom fields 1/2, the referral, offer, tracking and affiliate codes, member id, `utm_*`, `http_referer`; salutation, country, address type, payment method, card type and expiry, security code, transaction, authorization and reference codes, currency, IP.
- Also arrival date and shipping method codes, product name and description, recurring period, notes, product custom fields and tracking numbers. The tracking number import takes the guard off (`commerce/shipping-import-file-reading.md`).
- Left unguarded: ids, the order number, the card number cell (`#` + number), money, quantities, dates, yes/no. Product custom fields used to go through `escape_csv()` after the loop had doubled their quotes already (doubled twice).
- The site forms keep a posted salutation only when it is one of the pick list's values (`pg_salutation_value()`, `pg_liveform_salutation_values()` in `includes/fn/forms.php`): checkout billing and recipients, the classic billing and shipping pages, the address book and the account profile.
- Gift Cards export (`view_gift_cards.php`): notes, sender name, recipient e-mail, message and the two user names through `csv_cell()`; the shopper types the sender, recipient and message in the recipient form and order completion copies them as typed. Code, money and dates stay as they are.
- Visitor Report "Export All to CSV" (`view_visitor_report.php`): each grouping cell through `pg_csv_formula_guard()` after `update_summarize_by_name()`; referrer, search terms, tracking/affiliate codes and `utm_*` come from the URL (`?utm_source==1+1`). Visitor number, date, counts, 0/1 flags and Order Total stay unguarded. Neither export has an importer.
- The visitor report writes its lines with `pg_visitor_report_csv_line()` (`includes/fn/visitor_report.php`, RFC 4180: quotes doubled), not `fputcsv()`: with fputcsv's `\\` escape a visitor value `x\",=1+1` ended its cell early and put `=1+1` unguarded in the next one. The audit trail (`view_audit_log.php`) and ERP CSVs still use `fputcsv()`.
- Tests: `tests/csv_export_test.php`, `tests/csv_line_endings_test.php`, `tests/visitor_report_export_test.php`.

## Does an import take the guard off?
- `pg_csv_formula_unguard()` (beside the guard) drops a leading `'` only when `= + - @` tab or CR follows: the exact inverse. Import Submitted Forms runs it on every non-file cell (before the multi-value split) and the short link reader on every cell. Before, importing an unchanged submitted forms export stored `'+90 532…`, `'@pinegrap`, `'-5`, shown on the form screen and sent in e-mails.
- An answer typed as `'+1` loses its apostrophe on import too; the export cannot tell the two apart.
