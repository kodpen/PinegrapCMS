---
name: sheet-import-upload
description: How the CSV-only importers take .xlsx, .ods and .xls (pg_sheet_import_upload(), OS2a #10523): upload turned into a CSV file, the sheet question, refusal texts
module: sheet_import
---

## Which importers take workbooks this way?
- Contacts, Users, Submitted Forms, Key Codes, Campaign Profiles, Import Tracking Numbers, Short Links, ERP Import Accounts from CSV and Translations' Import CSV (census `plans/office-own-code-os2-census.md`). Products > Import and the bank statement import already read workbooks; they get only the sheet question, in OS2c.

## How does it work?
- The importer calls `pg_sheet_import_upload('file', <its screen title>)` after its token check and before it looks at the upload. A CSV, a missing file or a failed upload is left untouched for the importer's own checks.
- A workbook (`pg_sheet_kind()`) with one sheet: the sheet is written to a temporary CSV file (`pg_sheet_import_csv()`) and `$_FILES['file']` is replaced by it (same name, `tmp_name` the CSV, `size` the CSV's). The importer then reads it as any CSV: its separator guess, size and row limits, line numbers.
- Importers that check `is_uploaded_file()` call `pg_sheet_import_file($upload)` instead, which also takes the CSV this request made. The CSV files and a chosen workbook are removed when the request ends.

## What does the sheet question do?
- A workbook with more than one worksheet (`pg_sheet_names()`, chart sheets left out, in the reader's order) is moved to `data/temp/sheet_import_<token>.bin`, its name, kind and screen kept in the session (`sheet_import`), and a page with the screen's title asks "Sheet to import" (Continue, Cancel; Cancel goes to the screen, for Translations to its view through the `back` option).
- The question posts every field of the importer's form back as hidden inputs (not the old token), with `pg_sheet_token` and `pg_sheet`.
- The token is good for one import on the same screen within an hour (`PG_SHEET_IMPORT_MAX_AGE`); else "The upload has expired. Start again.". Each screen keeps its own gate, which runs again on the second post.

## What is the CSV it writes?
- UTF-8 without a mark, every cell quoted; a cell as `pg_sheet_cell_text()` gives it (boolean 1/0, date `Y-m-d`, with ` H:i:s` when it has a time). Rows above the header are dropped; a missing or empty row after it is a blank line, so a line number is the sheet row less those rows. A row is filled to the header's width.
- Separator: the importer's own when it passes one (Translations: `;`, the export's), else the comma, or `;` (else tab) when a heading holds a comma. A heading with a comma still reads as comma for the Key Codes guess, which counts inside quotes; its headings are fixed names.

## Which texts does a refusal show?
- Reader `read` → "The file could not be read."; `large` → "The file is too large."; `sheet` or no row with text → "The file was empty."; each shown in the importer's own refusal place. No new refusal text.
- The accept lists add `PG_SHEET_IMPORT_ACCEPT` (.xlsx,.ods,.xls); `pg_sheet_import_hint()` adds one line under each file field naming the formats.

## How is it tested?
- `tests/sheet_import_test.php`: `tests/fixtures/sheet_import/rows.*` (two sheets, LibreOffice 24.2) read by each importer's own CSV path give the same rows as `rows.csv`; the second sheet as short links equals `links.csv`; damaged files, hidden fields, texts. The upload and question pages themselves were not run in a browser.
