---
name: erp-api-dry-run
description: Which ERP API writes take X-Dry-Run (MCP dry_run) and which do not yet; the account create/update rehearsal (PR #1850): its checks, the opening lock asked before the card, the tests
---

## Which ERP writes can be rehearsed?
- Every document, stock, cash and quote/purchase-order write, and since PR #1850 `erp.accounts.create` and `erp.accounts.update`.
- Not yet: receipts (`create`, `cancel`) and expenses (`create`, `update`, `pay`, `cancel`, `receipt_put`); they come a PR per area (`plans/workspace-mcp-capabilities.md`, slice 8). A write without the flag refuses a rehearsal with 400 `dry_run_unsupported` (`api/api-dry-run.md`).

## What does an account rehearsal check?
- create: the tax number and a duplicate active card, e-mail, currency, the customer and an existing link, the name, and, with `opening_balance`, `erp_lock_refusal()` on the opening day (422 on `opening_date`). The stop sits above `erp_account_save()`; detail gives title, kind, currency, contact_id, opening_balance, opening_date.
- update: every field check and the link check; the stop sits above `erp_account_link_contact()`, so a rehearsal neither links nor saves. detail gives id and the changed field names.

## What changed for the real write?
- The opening lock is asked before the card is written: before PR #1850 a locked opening day left a new card behind with a 422 on `opening_balance`.

## Where is it tested?
- `tests/erp_api_test.php`: `test_erp_api_account_writes_can_be_rehearsed` (flag, stop before save and link, after the tax check, lock asked before save); documents, stock and cash in the tests above it.

Related: `api/api-dry-run.md`, `api/mcp-dry-run-argument.md`, `erp-api-seam.md`
