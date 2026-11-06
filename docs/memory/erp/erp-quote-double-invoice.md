---
name: erp-quote-double-invoice
description: Two presses at once on a quote's "Turn into an invoice": the quote row is locked before its state is read, so one quote makes one invoice draft.
---

## Why is a quote turned into an invoice under a lock?
- `erp_quote_to_invoice()` opens a transaction (`erp_tx_begin()`), locks the quote row (`SELECT … FOR UPDATE`), reads it with `erp_quote()` and judges it with `erp_quote_invoice_refusal()`, writes the draft (`erp_invoice_draft_save()` nests in it) and `status = 'invoiced'`, then commits. `erp.quote.invoiced` is announced after the commit.
- Before, two presses at once (the screen, `POST /erp/quotes/{id}/invoice` or a Workspace change card) both found the quote invoiceable and made two drafts at the quoted prices; issuing both billed the customer twice.
- Now the second request waits, then reads the quote as invoiced and is refused with "The quote has already become an invoice."
- Every refusal rolls back, so the lock is never held past the call.

Tests: tests/erp_quote_double_invoice_test.php. Related: docs/memory/erp/erp-quotes.md
