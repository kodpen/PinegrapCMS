---
name: erp-bank-statement-suggestions
description: ERP bank statement suggestions worked out once per statement (erp_bank_statement_suggestions): one line per open invoice, the per-line buttons, the count and Record the N suggested lines.
---

## Which lines get a suggestion?
- `erp_bank_statement_suggestions($lines)` works them out once for the whole statement: every new line outside the locked books, asked through `erp_bank_line_suggestion()`; one open invoice goes to the first line pointing at it, later lines get none (an account alone can go to several). The per-line Record for buttons, the count of Record the N suggested lines and that bulk action all use it, the bulk computing it before the first record.
- Before, each line was asked alone: two lines of one amount both offered the one open invoice, the bulk counted 2, recorded 1 and said "0 could not be", and the second line's button said "That invoice is already closed." Now a line the bulk could not record is named (date, amount, reason) in the notice.

What one line suggests (tax number, name, the one open invoice of this amount): `erp_bank_line_suggestion()`, erp-receipt-not-for-return. Tests: `tests/erp_bank_import_test.php`, `tests/erp_bank_statement_steps_test.php`.
