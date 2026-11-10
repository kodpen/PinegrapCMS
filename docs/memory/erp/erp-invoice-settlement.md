---
name: erp-invoice-settlement
description: ERP invoice closing by settlement (erp_settlements): why it posts no money, derived paid_total, no auto matching, transaction nesting, and why balance caches never feed a report.
---

## Why does settlement not post money?
- A receipt already credits the account and lowers the balance. `erp_settlements` adds no ledger row on top; it only records which movement closes which invoice.
- A second ledger row for closing would count the same money twice: the balance would diverge from cash by exactly the collected amount.

## What are the settlement rules?
- `UNIQUE KEY (invoice_id, account_txn_id)`: rewriting the same pair corrects the amount, never adds (`ON DUPLICATE KEY UPDATE`).
- `erp_invoices.paid_total` and `status` are derived from these rows (`erp_invoice_refresh_paid()`), never incremented (same reason as balances). `cancelled` and `draft` are left alone.
- A settlement exceeds neither the invoice's open amount nor the collected amount; the excess stays on the account.
- No automatic matching. Closing the oldest open invoice would silently break reconciliation; settlement is always somebody's decision (FIFO is only a suggestion, `erp_settlement_suggest()`). A caller that names the invoice (counter sale, bank statement line, cheque) settles it explicitly.
- `erp_post_receipt()`'s `invoice_id` writes the settlement inside the caller's transaction. A rejected settlement rolls back the whole receipt; no half-written receipt remains.
- A screen removes an allocation only through `erp_settlement_remove($id, $by, $owner)`, naming its record (`invoice_id` on the invoice screen, `account_txn_id` on the receipt screen). An allocation of another record is refused before anything is written. Before PR #5607 it was removed and then reported as not found, with no log line. Test: `tests/erp_settlement_remove_owner_test.php`.
- `erp_tx_begin/commit/rollback` keep nesting depth in one counter (`$GLOBALS['_erp_tx_depth']`, `erp_tx_depth()`); a second posting in the same request really opens a new transaction.

## How is a stored status shown?
- Through `erp_invoice_status_labels()` / `erp_invoice_status_label($status)` (`includes/erp/settlement.php`): the invoice list, the invoice screen, the File Manager's ERP section, the receipt screen's allocations (`erp_receipt.php`) and the invoice PDF (`includes/erp/document.php`, its own copy until #8544) print the same translated word. The allocations printed the raw code ("partially_paid") until #4109. Test: `tests/erp_invoice_status_labels_test.php`.

## Can a balance cache feed a report?
- No. `erp_accounts.balance` (`erp_account_refresh_balance()`, `includes/erp/ledger.php`) and the tills' `erp_cash_accounts.balance` (`erp_cash_refresh_balance()`, `includes/erp/cash.php`) are caches recomputed after each posting. Reconciliation and cash flow sum the ledgers (`erp_account_transactions`, `erp_cash_transactions`) as of their date; aging works from the documents and their settlements (erp-aging-and-due-date.md).
- Ledger rows are inserted only by `erp_cash_post()` (`cash.php`) and `erp_account_post()` (`ledger.php`); when a balance looks wrong, read those two first.

Related: docs/memory/erp/erp-receipt-corrections-and-gift-card.md

## How are two allocations at once kept apart?
`erp_settlement_allocate()` opens its transaction, locks the receipt's ledger row and then the invoice (`FOR UPDATE`, that fixed order) before any other read, and checks the amounts again with `erp_settlement_amount_refusal()`. Before (bug-hunt PR), both requests passed the unlocked check and over-allocated the invoice or the receipt. Still open: the allocation inside `erp_post_receipt()` (`cash.php`) reads the invoice without a lock. Test: `tests/erp_settlement_allocate_lock_test.php`.
