---
name: erp-parasut-sent-cancel
description: Why Cancel of a Paraşüt e-Fatura handed to GİB is refused from the row, the way out, what the deleted-record 404 can free wrongly after a company switch, and what is still open
---

## What does Cancel do for a `sent` e-Fatura?
- `erp_edoc_parasut_cancellable()` refuses it from the row ("An e-Invoice that has gone to GİB cannot be cancelled…"), also when no status question has read it since the hand-over and its conversion job may have failed. A cancelled order's card offers the return invoice with that reason; the invoice screen hides Cancel.
- Way out after a failed job: Ask After the Status. `poll` finds no e-document, the row becomes `created`, and Cancel deletes the draft at Paraşüt (erp-parasut-send-failures.md).

## Why is Paraşüt not asked at Cancel for such a row?
- Letting `cancellable()` answer ok for an unread `sent` e-Fatura (after the 15-minute job window) was tried in #PR and taken out before merge:
  - `cancel_invoice()` reads any 404 on `GET sales_invoices/{id}` as a deleted record, forgets it, and the ERP invoice is cancelled. A company switched on the settings card answers 404 for every older record, so an e-Fatura GİB holds would lose its ERP invoice without a return.
  - The order card would show its Cancel action instead of the return invoice for every unread e-Fatura; for one GİB holds, that Cancel fails on every click (`cancel_invoice()` refuses an `e_invoices` document).

## What can the deleted-record 404 free wrongly?
- `erp_edoc_parasut_gone_answer()` judges the row as the ERP knows it (#PR). After a company switch on the settings card, a `created` or `error` row may still stand for an e-document in the earlier company: one issued on Paraşüt's own screen, or by a hand-over whose answer was lost (`submit_invoice()` keeps no unsure mark; a failed hand-over leaves `created`). The status question frees it, and Send would issue the sale at GİB a second time; before #PR it stayed held. A guard would first check that the connected company holds the row's other records (for example the linked contact).

## What is still open?
- A safe version reads the document's state (`active_e_document`, the job) before any cancel and keeps the card's return step for an e-Fatura GİB holds. Candidates: the refusal's reason points to the status question while no status question has read the row; `cancel_invoice()` refuses a 404 on a row that may stand for a GİB document (`sent`, `accepted`, `cancelled`).
- A `sent` e-Fatura whose conversion failed and whose draft was then deleted on Paraşüt's screen, asked about only after the 15-minute job window (or after a PDF/Print request read and cleared the job): the send gate refuses it ("already gone"), `poll` answers the plain 404, and `cancellable()` refuses and offers a return invoice for a document GİB never had. No way out from the screens.
- The same 404 reading already reaches a `sent` or `accepted` e-Arşiv row, and a `sent` e-Arşiv whose job may still run goes to Paraşüt at once: with no active e-document yet, the record is DELETEd.
