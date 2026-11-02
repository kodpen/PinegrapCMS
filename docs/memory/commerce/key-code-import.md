---
name: key-code-import
description: Key code CSV import and export (import_key_codes.php, view_key_codes.php): a code already there is updated, not re-added; BOM, separator, dates, yes/no cells, formula guard, offer's own code
---

## What does the import do with a code that is already there?
- It is updated in the columns the file has, and only when one of them differs; then `user` and `timestamp` become the importer's. Nothing differs: the row is left alone and counted "unchanged".
- Before #735 (PR #1060) every code was deleted and inserted again: re-importing reopened used single-use codes and stamped every row.
- `key_codes.code` is not unique (the affiliate flows add one row per code and offer). When the file's offer code matches one of the code's rows, only those rows are compared and updated, so an export of a code with several offers imports back unchanged (before, each line stamped its offer code on all rows and one offer was lost). Otherwise every row with that code is updated.
- An enabled column in the file is written as given: re-importing an export taken before a single-use code was used opens it again. A file without the enabled column leaves enabled alone.

## And a new code?
- It needs an offer code that an offer has (the rule of `add_key_code.php`); the defaults are enabled 1, single use 0, report `key_code`, no expiration date, no notes.

## How are the cells read?
- Pure helpers in `includes/fn/ecommerce.php`, tested in `tests/key_code_import_test.php`: `pg_key_code_import_column()` (export headings, case, spaces, `_`/`-`, a BOM), `pg_key_code_import_separator()` (comma, semicolon or tab, whichever the header line holds most: Excel in Turkish writes `;`), `pg_key_code_import_flag()`, `pg_key_code_import_date()`, `pg_key_code_import_row()`.
- A UTF-8 BOM is stepped over by `pg_csv_open()` (core/csv-exports.md); the script reads the header line for the separator and seeks back to where that line started, never to 0, which would land on the mark (left in front of a quoted heading it kept the quotes in the name: "A key code column could not be found").
- Yes/no (enabled, single_use): 1/0, yes/no, true/false, Excel's TRUE/FALSE and DOĞRU/YANLIŞ, evet/hayır; empty leaves the column out.
- Expiration date: the export's Y-m-d; day.month.year with dots; slashes or dashes in `DATE_FORMAT` order, else the other order when that one is not a date; empty or 0000-00-00 is "does not expire". A two-digit year or a time is refused (before, MySQL stored it as 0000-00-00: never expires).
- Report: key_code or offer_code; empty leaves it out. Code and offer code: at most 50 characters (the column), not cut.
- Every cell goes through `pg_csv_formula_unguard()` first: the export (`pg_key_code_export_line()`) writes code, offer code, notes and user name through `csv_cell()` since #5728 (a code `=1+1` ran in Excel; affiliate codes are visitor-typed), so an unchanged export still reads back "unchanged".
- A bad yes/no cell names the column by its label (Enabled, Single-Use), not `enabled`/`single_use` (#5729).
- Notes and an empty expiration date are written as the file says, so export → import changes nothing.

## What is reported?
- A key code that is an offer's own code is skipped with the form's words (#5727).
- A row with a cell that cannot be stored, an unknown offer code or a new code without one is skipped whole; the list shows "Key codes imported: n added, n changed, n unchanged, n line(s) skipped." (also in the Site Log) and up to 20 skipped lines with their reason.
- A key code over 50 characters stays in `values['code']` with the error, so its row is listed, not taken for a blank line (a row without a code is passed over silently).
- "Line n" is the file line the row starts on: line breaks inside a quoted cell (a note) are counted.

## How was it checked?
- Sandbox: import, mark one code used, import again (stays used); an Excel-style file (BOM, `;`, 31.12.2026, DOĞRU/YANLIŞ, bad cells); the export imported back: all unchanged.

Related: docs/memory/commerce/key-code-form-checks.md
