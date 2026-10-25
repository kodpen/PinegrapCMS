---
name: erp-parasut-sent-cancel
description: Why Cancel of a Paraşüt e-Fatura handed to GİB is refused from the row even when its conversion job failed, the way out (a status question first), and what is still open
---

## What does Cancel do for a `sent` e-Fatura?
- `erp_edoc_parasut_cancellable()` refuses it from the row ("An e-Invoice that has gone to GİB cannot be cancelled…"), also when no status question has read it since the hand-over and its conversion job may have failed. A cancelled order's card offers the return invoice with that reason; the invoice screen hides Cancel.
- Way out after a failed job: Ask After the Status. `poll` finds no e-document, the row becomes `created`, and Cancel deletes the draft at Paraşüt (erp-parasut-send-failures.md).

## Why is Paraşüt not asked at Cancel for such a row?
- Letting `cancellable()` answer ok for an unread `sent` e-Fatura (after the 15-minute job window) was tried in #PR and taken out before merge:
  - `cancel_invoice()` reads any 404 on `GET sales_invoices/{id}` as a deleted record, forgets it, and the ERP invoice is cancelled. A company switched on the settings card answers 404 for every older record, so an e-Fatura GİB holds would lose its ERP invoice without a return.
  - The order card would show its Cancel action instead of the return invoice for every unread e-Fatura; for one GİB holds, that Cancel fails on every click (`cancel_invoice()` refuses an `e_invoices` document).

## What is still open?
- A safe version reads the document's state (`active_e_document`, the job) before any cancel and keeps the card's return step for an e-Fatura GİB holds. Candidates: the refusal's reason points to the status question while no status question has read the row; `cancel_invoice()` refuses a 404 on a row that may stand for a GİB document (`sent`, `accepted`, `cancelled`).
- The same 404 reading already reaches a `sent` or `accepted` e-Arşiv row, and a `sent` e-Arşiv whose job may still run goes to Paraşüt at once: with no active e-document yet, the record is DELETEd.
