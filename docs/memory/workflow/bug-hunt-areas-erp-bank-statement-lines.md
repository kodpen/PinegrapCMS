---
name: bug-hunt-areas-erp-bank-statement-lines
description: The bug hunt in ERP bank statement lines, matching and suggestions (agent channel request 3141, 2026-11-26): what was fixed, what was read clean, what is left
---

## Which area was it?
- ERP bank statement lines, matching and suggestions, not the file reader (hunted before): `includes/erp/bank_import.php` from `erp_bank_statement_delete()` to the end, and `erp_bank_statement.php`'s actions and line column.

## What was fixed?
- A line whose receipt, payment or expense was cancelled after it was recorded stayed recorded for good, so the bank's money could not be put back in the books. Now the screen says the movement was cancelled and offers Record again (`erp/erp-bank-statement-matching.md`).
- Two Take the lines in at once (two tabs) both built the statement: with other columns the lines came in twice, otherwise the count went to 0. A delete during the build left lines behind that turned the file away. The build and the delete now hold the statement's row lock (`erp/erp-bank-statement-matching.md`, `erp/erp-bank-statement-delete.md`).

## What was read and found clean?
- One line once: the hash and `INSERT IGNORE`; the record claim (`FOR UPDATE` on `status = 'new'`) in one transaction with the posting, so a line's button and Record the N suggested lines, pressed twice or together, post once.
- Matching: cancel rows and cancelled movements are no candidates; a movement used by any line (matched or recorded) is not matched again.
- Suggestions: worked out on the server at the bulk press from the lines as they are then, so a line changed since the page was drawn is left out; one open invoice goes to one line; a stale "Record for" button with a closed or cancelled invoice is refused by `erp_receipt_invoice_refusal()`.
- Delete: refuses recorded and matched lines under the line lock; Set aside, Bring back and Not this movement are conditional single updates.
- Money: amounts stay integer minor units; a payment from a bank till is not held by the cash-drawer balance rule.

## What is left?
- Matching runs only when the lines are taken in. A movement entered by hand afterwards is not matched, and the line can still be recorded, which posts the money a second time. Re-matching on each view would match again a line the operator just undid with Not this movement (nothing remembers the refused pair), so it is a product choice, asked in the PR.
- Two statements of one bank account built at the same moment can match the same movement; rare, not built.
- The line actions take a line id without checking it belongs to the statement in the address; the right asked (cash) is the same for every statement, so nothing is reached that the user could not reach anyway.
