---
name: erp-bank-statement-import
description: ERP bank statement import (includes/erp/bank_import.php): how a CSV's separator is chosen, how an amount's sign and decimals are read.
---

## How is a CSV statement's separator chosen?
- `erp_bank_csv_separator()` splits the first 50 non-empty lines with each candidate (`;`, `,`, tab, `|`) as CSV, quotes respected, and counts the lines of each column width above one. The candidate whose most common width covers the most lines wins; a tie goes to the earlier one (`;` first); no line of two columns falls back to `;`.
- Before (#1076, PR #1219) the character seen most often in the first ten lines won. A Turkish export (`;` separator, commas in descriptions, decimal commas) came apart on the commas: date and description in one cell, the amount in two, no right column to choose.
- The heading line decides the common case: it has no decimal commas, so `;` covers one line more than `,` even when every transaction carries the same number of commas.
- There is no separator choice on the column screen; a file the guess gets wrong cannot be fixed there.
- `str_getcsv()` is called with the escape argument (a backslash, the old default): PHP 8.4+ deprecates leaving it out.

## How is an amount's sign read?
- `erp_bank_amount()` looks at the text before the first digit and after the last: a minus (`-` or U+2212) before the number, a minus right after it, or `(` before and `)` after make it negative. `TL -1.234,56`, `₺-1.234,56`, `TRY − 1.234,56`, `1,234.56 - USD`, `TL (1.234,56)` are all money going out.
- Before (#1076) the minus was looked for only at the very start or end of the text, so a currency in front of it turned money out into money in.

## How are the decimals found?
- The last `,` or `.` is the decimal separator when one or two digits follow it; otherwise every separator is a thousands one (`1.234` is 1234,00).
- A separator with nothing in front of it is still the decimals: `.50` is 0,50. Before (#1076) `max(strrpos(','), strrpos('.'))` returned `false` when the only separator stood at position 0, and `.50` read as 50,00.

Tests: `tests/erp_bank_import_test.php`.
