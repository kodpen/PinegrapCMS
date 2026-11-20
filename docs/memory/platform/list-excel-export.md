---
name: list-excel-export
description: Export to Excel beside a panel list's CSV export (OS1, #10483): the capture helper, column types, the toolbar action, which lists have it, what passes through
module: list_excel
---

## What does Export to Excel do?
- Beside each panel list's CSV export (census: `plans/office-own-code-os1-census.md`) an "Export to Excel" action gives the same file as a one-sheet .xlsx: bold, frozen heading row, money with two decimals, dates as dates, numbers as numbers, the rest text.
- Written by the shared writer `pg_xlsx_write_plain()` (`includes/fn/xlsx_write.php`); no library.

## How does a list get it?
- The list asks for the Excel form of its own CSV: `pg_list_excel_capture($types, $sheet_title)` (`includes/fn/list_excel.php`) where its CSV export starts. With `excel=1` in the request it holds back all output (`ob_start` handler into `php://temp`); a shutdown function reads the CSV back and sends the workbook. Same rows, filters and permission as the CSV, by construction; no new permission.
- `$types` maps a heading text (or a zero-based column index) to `number`, `money` or `date`; unlisted columns are text. A cell that is not of its type stays text (e.g. a gift card's "Immediate" delivery date).
- The action: `pg_list_excel_action($csv_url)` (a `pg_screen_toolbar()` button), `pg_list_excel_link($csv_url, $class)` (a link with the CSV link's classes, `menu-item` in a menu), or `pg_list_excel_button($class, $attributes)` for a CSV export that is a button of a filter form (sends `excel=1` with the form's fields; the list's branch also opens on `pg_list_excel_asked()`: Orders, Contacts, Submitted Forms).

## What is read back from the CSV?
- The byte order mark is skipped; the separator is the comma, semicolon or tab most used in the heading line outside quotes. Read with an empty escape character from PHP 7.4 (exact RFC 4180), `\\` before.
- A semicolon file's decimal comma (ERP CSVs, `erp_csv_delimiter()`) is read as a decimal.
- Dates: ISO (`Y-m-d`, `Y-m-d H:i[:s]`) and the panel's own form (`pg_csv_export_time()`, `n/j/Y` or `j/n/Y` by `DATE_FORMAT`, 12 or 24 hours, the zone dropped: the cell shows the site's wall clock). A worded date (the visitor report's) stays text.
- Text loses the CSV formula guard (`pg_csv_formula_unguard()`): an inline string cell is never a formula.

## What passes through unchanged?
- A response that is not `text/csv` (a refusal page, a redirect, the orders "Multiple files" zip) and a response whose headers were already sent: the held-back output is printed as it was. A failed write (no `data/temp`) sends the CSV.
- A streaming export must not `flush()` while `pg_list_excel_capturing()`: it would send the CSV's headers (the visitor report skips its batch flush then).
- The file name is the CSV's with `.xlsx` (`pg_list_excel_disposition()`, the `filename*` UTF-8 name too); the workbook is built in `data/temp` and deleted after sending.

## Which writer parts did OS1 add?
- `array('money' => 12.5)`: cell style 4, built-in number format 4 (`#,##0.00`). `pg_xlsx_date_serial()` takes `Y-m-d H:i:s`; a date without seconds gives the same serial as before.
- Tests: `tests/list_excel_test.php`.
