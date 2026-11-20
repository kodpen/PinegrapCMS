---
name: sheet-import-upload
description: How the CSV-only importers take .xlsx, .ods and .xls (pg_sheet_import_upload(), OS2a #10523); the sheet question, also on Products > Import and the bank import (OS2c)
module: sheet_import
---

## Which importers take workbooks this way?
- Contacts, Users, Submitted Forms, Key Codes, Campaign Profiles, Import Tracking Numbers, Short Links, ERP Import Accounts from CSV and Translations' Import CSV (census `plans/office-own-code-os2-census.md`). Products > Import and the bank statement import read workbooks with their own readers and take only the sheet question (below).

## How does it work?
- The importer calls `pg_sheet_import_upload('file', <its screen title>)` after its token check and before it looks at the upload. A CSV, a missing file or a failed upload is left untouched for the importer's own checks.
- A workbook (`pg_sheet_kind()`) with one sheet: the sheet is written to a temporary CSV file (`pg_sheet_import_csv()`) and `$_FILES['file']` is replaced by it (same name, `tmp_name` the CSV); the importer reads it as any CSV.
- Importers that check `is_uploaded_file()` call `pg_sheet_import_file($upload)` instead, which also takes the CSV this request made. The CSV files and a chosen workbook are removed when the request ends.

## What does the sheet question do?
- A workbook with more than one worksheet (`pg_sheet_names()`, chart sheets left out, in the reader's order) is moved to `data/temp/sheet_import_<token>.bin`, its name, kind and screen kept in the session (`sheet_import`), and a page with the screen's title asks "Sheet to import" (Continue, Cancel; Cancel goes to the screen, for Translations to its view through the `back` option).
- The question posts every field of the importer's form back as hidden inputs (not the old token), with `pg_sheet_token` and `pg_sheet`.
- The token is good for one import on the same screen within an hour (`PG_SHEET_IMPORT_MAX_AGE`); else "The upload has expired. Start again.". Each screen keeps its own gate, which runs again on the second post.

## How do the product and bank imports ask?
- Two calls around the screen's own upload checks: `pg_sheet_import_workbook_answer($field, $error)` first puts a kept workbook back in `$_FILES` (its name, `tmp_name` the kept file) and returns the chosen sheet, else 0; `pg_sheet_import_workbook_ask($field, $title)` after the checks asks when an upload has more than one sheet (`pg_sheet_import_question_sheets()`).
- The sheet goes to `pg_products_import_to_csv(…, $sheet)` / `erp_bank_statement_upload(…, $sheet)`. A one-sheet workbook, a CSV or unreadable names go on as before; the bank screen's file check is `pg_sheet_import_file()`.

## What is the CSV it writes?
- UTF-8 without a mark, every cell quoted; a cell as `pg_sheet_cell_text()` gives it (boolean 1/0, date `Y-m-d`, with ` H:i:s` when it has a time). Rows above the header are dropped; a missing or empty row after it is a blank line, so a line number is the sheet row less those rows. A row is filled to the header's width.
- Separator: the importer's own when it passes one (Translations: `;`, the export's), else the comma, or `;` (else tab) when a heading holds a comma.

## Which texts does a refusal show?
- Reader `read` → "The file could not be read."; `large` → "The file is too large."; `sheet` or no row with text → "The file was empty."; each shown in the importer's own refusal place. No new refusal text.
- The accept lists add `PG_SHEET_IMPORT_ACCEPT` (.xlsx,.ods,.xls); `pg_sheet_import_hint()` adds one line under each file field naming the formats.

## How is it tested?
- `tests/sheet_import_test.php`: `tests/fixtures/sheet_import/rows.*` (two sheets, LibreOffice 24.2) read by each importer's own CSV path give the same rows as `rows.csv`; the second sheet as short links equals `links.csv`; damaged files, hidden fields, texts. `tests/sheet_import_workbook_test.php`: the product and bank imports read the chosen sheet, a one-sheet file does not ask, an answer puts the file back. The pages were not run in a browser.
