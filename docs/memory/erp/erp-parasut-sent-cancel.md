---
name: erp-parasut-sent-cancel
description: Why Cancel of a Paraşüt e-Fatura handed to GİB is refused from the row, the way out, the company guard on the deleted-record 404, and what is still open
---

## What does Cancel do for a `sent` e-Fatura?
- `erp_edoc_parasut_cancellable()` refuses it from the row ("An e-Invoice that has gone to GİB cannot be cancelled…"), also when no status question has read it since the hand-over and its conversion job may have failed. A cancelled order's card offers the return invoice with that reason; the invoice screen hides Cancel.
- Way out after a failed job: Ask After the Status. `poll` finds no e-document, the row becomes `created`, and Cancel deletes the draft at Paraşüt (erp-parasut-send-failures.md).

## Why is Paraşüt not asked at Cancel for such a row?
- Letting `cancellable()` answer ok for an unread `sent` e-Fatura (after the 15-minute job window) was tried in #1626 and taken out before merge:
  - `cancel_invoice()` reads any 404 on `GET sales_invoices/{id}` as a deleted record, forgets it, and the ERP invoice is cancelled. A company switched on the settings card answers 404 for every older record, so an e-Fatura GİB holds would lose its ERP invoice without a return.
  - The order card would show its Cancel action instead of the return invoice for every unread e-Fatura; for one GİB holds, that Cancel fails on every click (`cancel_invoice()` refuses an `e_invoices` document).

## What can the deleted-record 404 free wrongly?
- After a company switch on the settings card, a `created` or `error` row may still stand for an e-document in the earlier company (issued on Paraşüt's own screen, or by a hand-over whose answer was lost). Freeing it would let Send issue the sale at GİB a second time.
- Guard (#2025): `erp_edoc_parasut_gone_answer()` first reads another record the row names (`erp_edoc_parasut_gone_witnesses()`: the unsure mark's contact, the account's linked card, the lines' product cards; three at most). The first that answers as itself frees the row.
  - All answer 404: another company is taken to be connected. The row stays as it was (no forget, no `gone`), with a warning: connect the earlier company and ask again; if it has no e-document either, cancel the invoice and raise it again.
  - None named: held too, with the same way out (cancel deletes a 404 record as gone).
  - Any other answer (no token, timeout, 5xx, another id): nothing written.
  - A conversion job this question read as failed is the company's own record: freed without another read.
- Still open: a card re-linked or a product re-synced after the switch lives in the new company and frees the row. Never run live (demo key expired 2026-10-25).

## What is still open?
- A safe version reads the document's state (`active_e_document`, the job) before any cancel and keeps the card's return step for an e-Fatura GİB holds. Candidates: the refusal's reason points to the status question while no status question has read the row; `cancel_invoice()` refuses a 404 on a row that may stand for a GİB document (`sent`, `accepted`, `cancelled`).
- A `sent` e-Fatura whose conversion failed and whose draft was then deleted on Paraşüt's screen, asked about only after the 15-minute job window (or after a PDF/Print request read and cleared the job): the send gate refuses it ("already gone"), `poll` answers the plain 404, and `cancellable()` refuses and offers a return invoice for a document GİB never had. No way out from the screens.
- The same 404 reading already reaches a `sent` or `accepted` e-Arşiv row, and a `sent` e-Arşiv whose job may still run goes to Paraşüt at once: with no active e-document yet, the record is DELETEd.
