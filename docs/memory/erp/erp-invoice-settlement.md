---
name: erp-invoice-settlement
description: ERP invoice closing by settlement (erp_settlements): why it posts no money, derived paid_total, no auto matching, transaction nesting.
---

## Why does settlement not post money?
- A receipt already credits the account and lowers the balance. `erp_settlements` adds no ledger row on top; it only records which movement closes which invoice.
- A second ledger row for closing would count the same money twice: the balance would diverge from cash by exactly the collected amount.

## What are the settlement rules?
- `UNIQUE KEY (invoice_id, account_txn_id)`: rewriting the same pair corrects the amount, never adds (`ON DUPLICATE KEY UPDATE`).
- `erp_invoices.paid_total` and `status` are derived from these rows (`erp_invoice_refresh_paid()`), never incremented (same reason as balances). `cancelled` and `draft` are left alone.
- A settlement exceeds neither the invoice's open amount nor the collected amount; the excess stays on the account.
- No automatic matching (plan §550). Closing the oldest open invoice would silently break reconciliation; settlement is always somebody's decision.
- `erp_post_receipt()`'s `invoice_id` writes the settlement inside the caller's transaction. A rejected settlement rolls back the whole receipt; no half-written receipt remains.
- `erp_tx_begin/commit/rollback` keep nesting depth in one counter (`$GLOBALS['_erp_tx_depth']`, `erp_tx_depth()`); a second posting in the same request really opens a new transaction.

Related: docs/memory/erp/erp-receipt-corrections-and-gift-card.md
