---
name: erp-api-dry-run
description: Which ERP API writes take X-Dry-Run (MCP dry_run) and which do not yet; the account (PR #1850) and receipt (PR #1870) rehearsals: their checks, the *_check() functions, the tests
---

## Which ERP writes can be rehearsed?
- Every document, stock, cash and quote/purchase-order write, `erp.accounts.create`/`update` (PR #1850) and `erp.receipts.create`/`cancel` (PR #1870).
- Not yet: expenses (`create`, `update`, `pay`, `cancel`, `receipt_put`); they come a PR per area (`plans/workspace-mcp-capabilities.md`, slice 8). A write without the flag refuses a rehearsal with 400 `dry_run_unsupported` (`api/api-dry-run.md`).

## What does an account rehearsal check?
- create: the tax number and a duplicate active card, e-mail, currency, the customer and an existing link, the name, and, with `opening_balance`, `erp_lock_refusal()` on the opening day (422 on `opening_date`). The stop sits above `erp_account_save()`; detail gives title, kind, currency, contact_id, opening_balance, opening_date.
- update: every field check and the link check; the stop sits above `erp_account_link_contact()`, so a rehearsal neither links nor saves. detail gives id and the changed field names.

## What does a receipt rehearsal check?
- The panel's own checks, split out of the writers in `includes/erp/cash.php`: `erp_post_receipt_check()` (amount, account, till, period lock, payment method, currency and rate, till currency, the named invoice) and `erp_receipt_cancel_check()` (exists, not cancelled, not a cheque's movement, period lock, reason, ledger row). The writers call them first, so panel and API meet one set of refusals.
- `erp_receipt_invoice_refusal()` judges the invoice (the account's, an invoice not a return, same currency, right side, still open); `erp_post_receipt()` asks it again inside its transaction, since the invoice may close meanwhile.
- detail: create gives account, till, direction, amount, date, invoice_id; cancel gives id, amount and the invoices that would open again.

## What changed for the real write?
- The opening lock is asked before the card is written: before PR #1850 a locked opening day left a new card behind with a 422 on `opening_balance`.

## Where is it tested?
- `tests/erp_api_test.php`: `test_erp_api_account_writes_can_be_rehearsed` (flag, stop before save and link, after the tax check, lock asked before save), `test_erp_api_receipt_writes_can_be_rehearsed` (flag, check before stop before write, the writers call the checks); documents, stock and cash in the tests above it.

Related: `api/api-dry-run.md`, `api/mcp-dry-run-argument.md`, `erp-api-seam.md`
