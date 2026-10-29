---
name: csv-exports
description: Panel CSV exports start with pg_csv_download_start() (UTF-8 charset and byte order mark) and stamp times with pg_csv_export_time() (site clock); pg_csv_open() steps over the mark on import
---

## How does a panel CSV export start?
- `pg_csv_download_start($filename)` (`includes/fn/core.php`) sends `Content-Type: text/csv; charset=utf-8` and the attachment header (`pg_csv_download_headers()`), then writes the UTF-8 byte order mark `EF BB BF`.
- Without the mark Excel on Windows opens the file in the ANSI code page: Turkish letters in names, addresses, notes and answers showed as `Ã¼` / `ÅŸ`. Used by the contacts, gift card, key code, submitted form and campaign profile exports; the visitor report, short link and ERP exports already wrote the mark themselves.

## How is a time written in an export?
- `pg_csv_export_time($timestamp)`: the site's day/month order, the site clock with seconds and the zone (`4/3/2026 17:05:09 +03` on a 24-hour site, `4/3/2026 5:05:09 PM +03` otherwise). Before, these exports used the fixed `g:i:s A T` (12-hour on every site).

## Which words are translated?
- Words the export writes in place of data follow the panel language like the list screen: the gift card `Anonymous` sender and `Immediate` delivery, the submitted form `Complete` / `Incomplete` status. No importer reads those columns.
- Column headings stay English: the importers read them.

## What does the import side do with the mark?
- `pg_csv_open()` steps over a leading `EF BB BF` in its direct and lone-CR copy paths, so every importer reads the first heading without it. Before, `import_contacts.php` dropped the first column, `import_email_campaign_profiles.php` refused the file (no `name` column) and `import_submitted_forms.php` missed a first field name for any file Excel saved as "CSV UTF-8".
- `import_key_codes.php` reads its header line for the separator and seeks back to where that line started (`ftell()`), not to 0, which would land on the mark again.

## What was left as it is?
- The comma separator, money decimals and no formula guard on the older exports: separate decisions. `view_orders.php` has its own export task.
- Tests: `tests/csv_export_test.php`, `tests/csv_line_endings_test.php`.
