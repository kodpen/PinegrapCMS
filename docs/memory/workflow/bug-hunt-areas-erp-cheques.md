---
name: bug-hunt-areas-erp-cheques
description: The bug hunt of ERP cheques and promissory notes (2026-11-26, PR #11844): the five faults fixed, the product question asked, what was read clean and what was left
---

## Which area was hunted?
- Architect (2026-11-26, agent channel request 3141): ERP cheques and promissory notes, `includes/erp/cheques.php`, `erp_cheques.php`, `erp_cheque.php`, the cheque handlers of `includes/erp/api_cash.php`, and the cheque gates in `erp_receipt_cancel_check()` and `erp_settlement_allocate()`.

## What did the hunt's PR fix? (PR #11844)
- The screen passed a cheque on (endorse) to the customer it came from or to an inactive account: only the API refused. `erp_cheque_endorse_account_refusal()` is asked in `erp_cheque_step()`.
- The screen's bank list dropped a bank taken off after the cheque went to it, so Mark as collected moved the money into the first active till. `erp_cheque_bank_till_choices_where()` keeps it listed.
- The API refused that stored inactive bank and skipped the currency check on a deposit; both ask `erp_cheque_bank_till_id_refusal()` now.
- A step date the screen could not read became today; `erp_cheque_typed_step_date()` passes it on to be refused.
- The API's dry run passed a step dated before the cheque; it asks `erp_cheque_step_date_refusal()` on the `date` field first.

## What was asked, not built?
- A cheque given to a supplier, or one passed on, that comes back unpaid has no step (only received ones bounce). A product choice for the architect, named in the PR body.

## What was read clean?
- A step sent twice: the claim `FOR UPDATE` on the status, the deposit's guarded `UPDATE`, the API re-reading the status after a refused step.
- No step rewrites an earlier movement: each is a new receipt or transfer dated its own day; the bounce takes allocations off with `erp_txn_unsettle_all()`.
- Receipt cancel: `erp_cheque_for_cash()` gates both the screen and `POST /erp/receipts/{id}/cancel`; a transfer leg is no receipt and has no cancel.
- Period lock: `erp_post_receipt()` and `erp_post_transfer()` ask `erp_lock_refusal()`; the API asks up front but for a deposit, which moves no money.
- Till right: both screens gate on `validate_erp_access($user, 'cash')`, which refuses a read-only accountant's POST; the API on `erp_api_expense_cash_gate()`.

## What was left?
- A bounce and an allocation of the same cheque's receipt pressed at the same moment: the allocation reads the status without the cheque's row lock. Not reproduced; no database here.
- No database run: the fixes are pinned by `tests/erp_cheque_step_checks_test.php` and `tests/erp_cheque_bank_till_test.php`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/erp/erp-cheques.md
