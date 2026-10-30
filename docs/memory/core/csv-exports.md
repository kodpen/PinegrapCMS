---
name: csv-exports
description: Panel CSV exports start with pg_csv_download_start() (UTF-8 charset and byte order mark) and stamp times with pg_csv_export_time() (site clock); pg_csv_open() steps over the mark on import
---

## How does a panel CSV export start?
- `pg_csv_download_start($filename)` (`includes/fn/core.php`) sends `Content-Type: text/csv; charset=utf-8` and the attachment header (`pg_csv_download_headers()`), then writes the UTF-8 byte order mark `EF BB BF`.
- Without the mark Excel on Windows opens the file in the ANSI code page: Turkish letters in names, addresses, notes and answers showed as `Ã¼` / `ÅŸ`. Used by the contacts, gift card, key code, submitted form, campaign profile and orders (single file) exports; the visitor report, short link and ERP exports already wrote the mark themselves.
- Orders "Multiple files" zip (`view_orders.php`): each entry (`orders.csv`, `ship_tos.csv`, `order_items.csv`, `shipping_tracking_numbers.csv`) starts with the mark. The zip holds one file per kind of record, not per order; the menu item was mislabelled "One file per order" until PR #3587.
- Products CSV (`pg_products_export_csv()` in `export_products_f.php`, behind Products > Export and the File/Catalog Manager): writes the mark itself after its own headers, because `pg_products_export_disposition()` carries the `filename*` UTF-8 file name that `pg_csv_download_start()` would lose. `import_products.php` reads through `pg_csv_open()`; the Workspace table reader strips the mark too.

## How is a time written in an export?
- `pg_csv_export_time($timestamp)`: the site's day/month order, the site clock with seconds and the zone (`4/3/2026 17:05:09 +03` on a 24-hour site, `4/3/2026 5:05:09 PM +03` otherwise). Before, these exports (the orders `order_date` included) used the fixed `g:i:s A T` (12-hour on every site).

## Which words are translated?
- Words the export writes in place of data follow the panel language like the list screen: the gift card `Anonymous` sender and `Immediate` delivery, the submitted form `Complete` / `Incomplete` status. No importer reads those columns.
- Column headings stay English: the importers read them.

## What does the import side do with the mark?
- `pg_csv_open()` steps over a leading `EF BB BF` in its direct and lone-CR copy paths, so every importer reads the first heading without it. Before, `import_contacts.php` dropped the first column, `import_email_campaign_profiles.php` refused the file (no `name` column) and `import_submitted_forms.php` missed a first field name for any file Excel saved as "CSV UTF-8".
- `import_key_codes.php` reads its header line for the separator and seeks back to where that line started (`ftell()`), not to 0, which would land on the mark again.

## What was left as it is?
- The comma separator, money decimals and no formula guard on the older exports: separate decisions. Orders keep their snake_case headings, point-decimal money and the `order_items.tax` column; products keep the headings `import_products.php` reads.

## Which separator does the ERP audit trail CSV use?
- `erp_audit_csv()` (`includes/erp/audit.php`) uses `erp_csv_delimiter()` like the other ERP CSVs: `;` where the store uses a decimal comma, `,` elsewhere. It used a fixed `;`, so on a point-decimal store Excel put each line in column A. Dates stay `Y-m-d H:i:s`, amounts point-decimal (as in the ERP export profiles).

## Which cells get the formula guard?
- `pg_csv_formula_guard()` (`includes/fn/core.php`) puts `'` before a leading `= + - @` tab or CR; `csv_cell()` and `pg_audit_view_csv_line()` use it. The ERP audit trail (user, action, number or name, details) and aging (account title) CSVs use it since PR #NNNN: a web order's billing name `=1+1` ran in Excel. Never on amounts (`-12.50`), dates, ids, or in `erp_export_write_csv()` (the ERP importer reads profiles back).
- Tests: `tests/csv_export_test.php`, `tests/csv_line_endings_test.php`.
