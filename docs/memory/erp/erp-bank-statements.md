---
name: erp-bank-statements
description: ERP bank statement import (4.102): readers, the PHPExcel libxml trap, title-block cut, CSV separator, amount sign and decimals; the lines are in erp-bank-statement-lines.md.
---

## Which files are read? (`includes/erp/bank_import.php`, 2026.4.4 step 4.102)
- CSV (`erp_bank_read_csv()`); `.xlsx`/`.xlsm` walked row by row from the zip with the product importer's helpers (`pg_products_import_xlsx_*()` in `import_products_f.php`, XMLReader, no library); `.xls` and `.ods` with PHPExcel (`pg_products_import_reader()`, read with formats: a date cell is known only by its format; `.ods` ceilings: docs/memory/platform/ods-cell-ceiling.md). `.numbers` and `.pdf` are refused by name. At most `ERP_BANK_IMPORT_MAX_ROWS` (3000) rows.
- The columns chosen are remembered per bank account (the last statement's `mapping`).
- Every format goes through `erp_bank_read_file()` and comes out as the same rows of cells: a number cell becomes CSV-like text (`erp_bank_sheet_value()`: a date-formatted cell `Y-m-d`, other numbers with a point and two decimals), and the bank's title block is cut off (`erp_bank_rows_from_heading()`: the row above the first dated amount is the heading).
- A dated amount is `erp_bank_cells_are_movement()`: a date cell and a non-zero amount cell (digits, at most 3 letters). A date cell holding `;`, tab or `|` is a line split on the wrong separator and does not count; the amount is compared without an `(int)` cast, which wraps to 0 at 2^32 kurus on 32-bit PHP.
- Trap: PHPExcel switches libxml's external entity loader off (`libxml_disable_entity_loader(true)` in `PHPExcel_Settings`) and leaves it so; an `.xlsx` opened with XMLReader later in the same request then fails. (`pg_office_zip_stream()` in `includes/fn/office.php` switches it back on for its own reads.)

## How is a CSV's separator chosen? (#1076, PR #1219)
- `erp_bank_csv_separator()` splits the first 50 non-empty lines with each candidate (`;`, `,`, tab, `|`) as CSV, quotes respected. The one that reads the most lines as dated amounts wins; a tie goes to the one whose most common column width covers more lines, then to the earlier one; `;` is the fallback.
- Before, the most frequent character in the first ten lines won: a Turkish `;` export with commas in descriptions and decimal commas split on the commas (amount in two cells). Column counts alone still let a comma title block or a heading-less tab file tip it to `,`.
- `str_getcsv()` gets its escape argument (PHP 8.4+ deprecation).

## Can the operator choose the separator? (PR #1343)
- Yes, on the column screen of a CSV statement: Automatic (the guess above), `;`, `,`, tab, `|`. The form posts a name (`erp_bank_csv_separators()`), not the character (a tab could be trimmed).
- A CSV statement keeps its lines until the columns are chosen (`raw_rows` = `{"csv": [lines]}`); `erp_bank_statement_rows()` splits them and cuts the title block on every read. A workbook, or a CSV taken in before, keeps its cells (a list): no select.
- The name is stored in the statement's `mapping` (`separator`): the import uses the split shown, and the bank account's next statement starts with it.

## How is an amount's sign and decimals read? (`erp_bank_amount()`, PR #1219)
- Negative: a minus (`-`, U+2212) before the first digit, also after `TL`, `₺`, `TRY`; one right after the last digit or ending the text (`1.234,56 TL-`); or `(` before and `)` after the number. Before, only the very ends of the text were looked at, so `TL -1.234,56` was money in.
- The last `,` or `.` is the decimal separator when one or two digits follow it; otherwise all are thousands (`1.234` is 1234,00). `.50` is 0,50 (it read 50,00: `max(false, 0)`).
- A statement taken in before PR #1219 and again gets a new `line_hash` for each line whose amount now reads differently, and those lines are added again.

What happens to the lines once read (`line_hash` and repeated lines, matching, recording): `erp-bank-statement-lines.md`.
