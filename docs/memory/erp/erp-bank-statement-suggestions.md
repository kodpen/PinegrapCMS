---
name: erp-bank-statement-suggestions
description: ERP bank statement suggestions worked out once per statement (erp_bank_statement_suggestions): one line per open invoice, the per-line buttons, the count and Record the N suggested lines.
---

## Which lines get a suggestion?
- `erp_bank_statement_suggestions($lines)` works them out once for the whole statement: every new line outside the locked books, asked through `erp_bank_line_suggestion()`; one open invoice goes to the first line pointing at it, later lines get none (an account alone can go to several). The per-line Record for buttons, the count of Record the N suggested lines and that bulk action all use it, the bulk computing it before the first record.
- Before, each line was asked alone: two lines of one amount both offered the one open invoice, the bulk counted 2, recorded 1 and said "0 could not be", and the second line's button said "That invoice is already closed." Now a line the bulk could not record is named (date, amount, reason) in the notice.

## How is an account's name found in a line?
- `erp_bank_line_names()` folds the line's words and the account's name alike with `erp_bank_name_fold()`: `pg_list_search_fold()` (İ, I, ı, i one letter), then ş ç ğ ö ü to ASCII and runs of space to one; a name of at least six letters must appear. Before, `mb_strtolower()` turned `İ` into i + U+0307 and `I` into i, so a capitals line ("EFT MÜŞTERİ A.Ş.", "YILMAZ") never named its account and only the one-open-invoice rule could suggest one (#3999, PR #4184). The line's hash key (`erp_bank_line_key()`, erp-bank-statement-lines) keeps `mb_strtolower()`: stored hashes depend on it.

What one line suggests (tax number, name, the one open invoice of this amount): `erp_bank_line_suggestion()`, erp-receipt-not-for-return. Tests: `tests/erp_bank_import_test.php`, `tests/erp_bank_statement_steps_test.php`, `tests/erp_bank_name_fold_test.php`.
