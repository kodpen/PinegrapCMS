---
name: erp-bank-statements
description: ERP bank statement import (4.102): formats and readers, the PHPExcel libxml trap, the title-block cut, each line once (line_hash), matching within 3 days, recording via receipt or expense.
---

## Which files are read? (`includes/erp/bank_import.php`, 2026.4.4 step 4.102)
- CSV (`erp_bank_read_csv()`); `.xlsx`/`.xlsm` walked row by row from the zip with the product importer's helpers (`pg_products_import_xlsx_*()` in `import_products_f.php`, XMLReader, no library); `.xls` and `.ods` with PHPExcel (reader from `pg_products_import_reader()`, read with formats because a date cell is known only by its format; an `.ods` is opened by the product importer's `pg_products_import_ods_load()` with its part, cell and used-area ceilings: docs/memory/platform/ods-cell-ceiling.md). `.numbers` and `.pdf` are refused by name. At most `ERP_BANK_IMPORT_MAX_ROWS` (3000) rows.
- Every format goes through `erp_bank_read_file()` and comes out as the same rows of cells: a number cell becomes CSV-like text (`erp_bank_sheet_value()`: a date-formatted cell `Y-m-d`, other numbers with a point and two decimals), and the bank's title block is cut off (`erp_bank_rows_from_heading()`: the row above the first dated amount is the heading).
- Trap: PHPExcel switches libxml's external entity loader off (`libxml_disable_entity_loader(true)` in `PHPExcel_Settings`) and leaves it so; an `.xlsx` opened with XMLReader later in the same request then fails. (`pg_office_zip_stream()` in `includes/fn/office.php` switches it back on for its own reads.)

## How is a line taken once and matched?
- A line is a hash of till, day, amount and words (`line_hash`, `INSERT IGNORE` under `UNIQUE (cash_account_id, line_hash)`): a statement taken in twice, or two that overlap, add each line once.
- Matching (`erp_bank_statement_match()`): a till movement on the same till, same direction and amount, within ±3 days and not yet used by another line; the nearest date wins.
- The columns chosen are remembered per bank account (the last statement's `mapping`).

## How is an unmatched line recorded?
- Nothing is written until the operator says so. `erp_bank_line_record()`: a collection or payment on an account through `erp_post_receipt()` (closing an invoice when one is named), or an expense paid from the bank through `erp_expense_save()` (VAT 0, not deductible). Or the line is set aside (`erp_bank_line_ignore()`).
- There is no bank API connection: statements come as files.
