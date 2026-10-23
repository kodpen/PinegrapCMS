---
name: erp-bank-statement-lines
description: ERP bank statement lines: each line once (line_hash with an occurrence count for repeated lines), matching to till movements, recording an unmatched line.
---

## How is a line taken once? (`erp_bank_statement_build()`)
- A line is stored once per bank account: `UNIQUE (cash_account_id, line_hash)` + `INSERT IGNORE`. Its hash is `erp_bank_line_hash()`: sha1 of account, day, amount and lower-cased words (`erp_bank_line_key()`), plus the line's occurrence of those in the file, counted by `erp_bank_line_occurrence()` only for rows that reach the INSERT.
- The first occurrence has no suffix and hashes exactly as before, so stored lines still turn a re-upload away. The second gets `|2`, the third `|3`. The same file, or an overlapping one that holds every repeat, adds nothing.
- Before (#1075, PR #1223) two -8,50 transfer fees on one day were one line: the second was skipped as "taken in before" and the till stayed 8,50 off the bank. Re-uploading a statement taken in before the change adds only the repeat dropped then.
- Known limit: an overlapping file that starts between two such lines holds only the later one, counts it as the first and skips it. Taking in whole days avoids it.

## Which traps does the hash key hold?
- The amount goes into the key uncast: past 2^31 kurus (21.474.836,48) `erp_bank_amount()` gives a float on 32-bit PHP, and an `(int)` cast would wrap it onto another amount and change the hash of a line stored before.
- On PHP 7.3+ `mb_strtolower()` turns `İ` into `i` + U+0307 (full case mapping); on 7.1/7.2 it gives a plain `i`. So `EFT ÜCRETİ` and `Eft Ücreti` hash differently on 7.3+, and moving a site from 7.2 to 7.3+ changes the hash of stored lines containing `İ`. Kept so that stored hashes still match.

## How is a line matched?
- `erp_bank_statement_match()`: a till movement on the same till, same direction and amount, within ±3 days and not yet used by another line (`NOT EXISTS`, so two identical lines pair with two movements); the nearest date wins.

## How is an unmatched line recorded?
- Nothing is written until the operator says so. `erp_bank_line_record()`: a collection or payment through `erp_post_receipt()` (closing an invoice when one is named), or an expense paid from the bank through `erp_expense_save()` (VAT 0, not deductible). Or the line is set aside (`erp_bank_line_ignore()`). No bank API: statements come as files.

Reading the file, CSV separator and amount: `erp-bank-statements.md`. Tests: `tests/erp_bank_import_test.php`. The invoice a line suggests: erp-receipt-not-for-return.
