---
name: erp-bank-statement-matching
description: ERP bank statement matching: which till movements a line matches, cancels left out, undoing a match, which tills take a statement.
---

## How is a statement built once?
- `erp_bank_statement_build()` claims the statement (`SELECT status … FOR UPDATE`, still `mapping`) and adds, counts and matches its lines in one transaction; the delete takes the same row lock first. Before (bug hunt, 2026-11-26) a second Take the lines in from another tab added every line again under other columns, or set the line count to 0.

## How is a line matched?
- `erp_bank_statement_match()`: a till movement on the same till, same direction and amount, within ±3 days and not yet used by another line (`NOT EXISTS`, so two identical lines pair with two movements); the nearest date wins.
- A cancel row (`doc_type = 'cancel'`) and the movement it cancels (`doc_id`) are no candidates. Before (#5584) a line matched them and was never recorded.
- "Not this movement" (`erp_bank_line_unmatch()`) puts a `matched` line back to `new`; a `recorded` one stays, unless its movement was cancelled since.

## What happens when a line's movement is cancelled afterwards?
- `erp_bank_statement_lines()` reads `cash_cancelled` (a `cancel` row whose `doc_id` is the line's `cash_id`). The screen says "The movement was cancelled, so this bank line is not in the books." and offers Record again on a recorded line (Not this movement on a matched one); both go through `erp_bank_line_unmatch()`, which reopens a recorded line only when that cancel row exists. The rule is `erp_bank_line_reopenable()`.
- Before (bug hunt, PR of 2026-11-26) a receipt, payment or expense cancelled after its line was recorded left the line recorded for good: the bank showed the money, the books did not, and the line could not be recorded again.
- Nothing reopens a line by itself: the operator decides whether the money is recorded again.

## Which tills take a statement?
- Only `erp_bank_statement_kinds()` (`includes/erp/bank_import.php`): bank, card terminal, credit card; the upload list (`erp_bank_statements.php`) and `erp_bank_statement_upload()` both ask `erp_bank_statement_kinds_sql()`. Before (#5586) a cash drawer or a cheque portfolio could take a statement, and its lines matched and were recorded on that till.

## Why does the scroll box hold a combobox's select?
- `.pg-combobox-native` is `position: absolute`; in a `.table-responsive` that is not positioned, its containing block is outside the scroll box, so the overflow does not clip it and the page grew sideways on a phone (#5585, the Record form at 375 px). Every `.table-responsive` is `position: relative` in backend.src.css since #9079 (`ui/scrolling-table-hidden-text.md`).

Tests: `tests/erp_bank_statement_bugs_test.php`, `tests/erp_bank_statement_cancelled_test.php`.
