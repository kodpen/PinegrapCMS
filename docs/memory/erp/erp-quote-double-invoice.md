---
name: erp-quote-double-invoice
description: Two presses at once on a quote's "Turn into an invoice": the quote row is locked before its state is read, and a status move is guarded, so one quote makes one invoice draft.
---

## Why is a quote turned into an invoice under a lock?
- `erp_quote_to_invoice()` opens a transaction (`erp_tx_begin()`), locks the quote row (`SELECT … FOR UPDATE`), reads it with `erp_quote()` and judges it with `erp_quote_invoice_refusal()`, writes the draft (`erp_invoice_draft_save()` nests in it) and `status = 'invoiced'`, then commits. `erp.quote.invoiced` is announced after the commit.
- Before, two presses at once (the screen, `POST /erp/quotes/{id}/invoice` or a Workspace change card) both found the quote invoiceable and made two drafts at the quoted prices; issuing both billed the customer twice.
- Now the second request waits, then reads the quote as invoiced and is refused with "The quote has already become an invoice."
- Every refusal rolls back, so the lock is never held past the call.

## Why is a status move guarded?
- `erp_quote_set_status()` (screen buttons, `POST /erp/quotes/{id}/status`, the guest link's acceptance, the screen signature) updates `WHERE status = <read> AND invoice_id = <read>`. No row moved: the refusal is read again from the quote and nothing is announced; a failed write is reported.
- Before, a move read while the quote was open, written after another request invoiced it, overwrote `invoiced`: the quote read as accepted with its draft still there and could be invoiced a second time (bug hunt 2026-11-26).

Tests: tests/erp_quote_double_invoice_test.php, tests/erp_quote_status_guard_test.php. Related: docs/memory/erp/erp-quotes.md
