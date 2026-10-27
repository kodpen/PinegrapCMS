---
name: erp-api-dry-run
description: Which ERP API writes take X-Dry-Run (MCP dry_run); the account (PR #1850), receipt (PR #1870) and expense (PR #1891) rehearsals: their checks, the *_check() functions, the tests
---

## Which ERP writes can be rehearsed?
- Every ERP write: documents, stock, cash, quotes/purchase orders, `erp.accounts.create`/`update` (PR #1850), `erp.receipts.create`/`cancel` (PR #1870) and `erp.expenses.create`/`update`/`pay`/`cancel`/`receipt_put` (PR #1891). A write without the flag refuses a rehearsal with 400 `dry_run_unsupported` (`api/api-dry-run.md`).

## What does an account rehearsal check?
- create: the tax number and a duplicate active card, e-mail, currency, the customer and an existing link, the name, and, with `opening_balance`, `erp_lock_refusal()` on the opening day (422 on `opening_date`). The stop sits above `erp_account_save()`; detail gives title, kind, currency, contact_id, opening_balance, opening_date.
- update: every field check and the link check; the stop sits above `erp_account_link_contact()`, so a rehearsal neither links nor saves. detail gives id and the changed field names.

## What does a receipt rehearsal check?
- The panel's own checks, split out of the writers in `includes/erp/cash.php`: `erp_post_receipt_check()` (amount, account, till, period lock, payment method, currency and rate, till currency, the named invoice) and `erp_receipt_cancel_check()` (exists, not cancelled, not a cheque's movement, period lock, reason, ledger row). The writers call them first, so panel and API meet one set of refusals.
- `erp_receipt_invoice_refusal()` judges the invoice (the account's, an invoice not a return, same currency, right side, still open); `erp_post_receipt()` asks it again inside its transaction, since the invoice may close meanwhile.
- detail: create gives account, till, direction, amount, date, invoice_id; cancel gives id, amount and the invoices that would open again.

## What does an expense rehearsal check?
- The panel's checks, split out of the writers in `includes/erp/expenses.php`, which call them first: `erp_expense_save_check()` (everything `erp_expense_save()` asked before its transaction; returns the columns it would write), `erp_expense_pay_check()` (waiting to be paid), `erp_expense_cancel_check()` (reason, not cancelled, lock on the expense and on its payment's movement) and `erp_expense_receipt_check()` (file kept, may be replaced, size, type).
- `erp_expense_payment_refusal()` judges a payment (method, lock, not after today, not before the expense, the till). Create with `cash_account_id` meets it in the save check, so a rehearsal and the real call refuse a bad till before the expense row is inserted; `erp_expense_post_payment()` asks it again under the row lock.
- Pay and cancel read the row again `FOR UPDATE`; cancel refuses ("could not be cancelled") when the status moved since its check.
- detail: create gives date, category_id, currency, net/tax/total, paid, cash_account_id, receipt; update id and the sent fields; pay id, till, amount, date; cancel id, amount, the till a paid one goes back to; receipt_put (`would` created or replaced) id, type, size.

## What changed for the real write?
- The opening lock is asked before the card is written: before PR #1850 a locked opening day left a new card behind with a 422 on `opening_balance`.

## Where is it tested?
- `tests/erp_api_test.php`: `test_erp_api_account_writes_can_be_rehearsed` (flag, stop before save and link, after the tax check, lock asked before save), `test_erp_api_receipt_writes_can_be_rehearsed` and `test_erp_api_expense_writes_can_be_rehearsed` (flag, check before stop before write, the writers call the checks); documents, stock and cash in the tests above it.

Related: `api/api-dry-run.md`, `api/mcp-dry-run-argument.md`, `erp-api-seam.md`
