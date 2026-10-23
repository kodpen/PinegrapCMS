---
name: erp-edoc-send-and-cancel
description: How an ERP invoice goes through the e-document provider: the send gate, a send that stopped, create then hand to GİB (autosend, 4.63), status on request, taking a document back before the cancel.
---

## When may an invoice be sent? (`erp_edoc_invoice_can_send()`)
- An active provider whose driver sends; a sales document, issued (not draft or cancelled); a return only when the driver declares `return` (neither Paraşüt nor İşbaşı does: the return stays in the ERP, its e-document is made on the provider's screen); no second tax (`tax2_total`: a GİB document cannot show it, the invoice stays a PDF).
- `edoc_status` `created` is refused (hand that draft over instead of creating a second); `queued`, `sending`, `sent`, `accepted` are refused (ask after its status), except a stalled send (below).

## What if a send stops before the provider answers?
- A request that ends inside the driver call (Windows counts the provider wait in `max_execution_time`, a killed process, a fatal error) leaves `sending` with no `edoc_external_id`. `erp_edoc_invoice_send_stalled()`: such a row last written over 15 minutes ago. The gate lets it go again like `error` without an id, and the driver's own check runs (Paraşüt's unsure mark); a fresh `sending` row is still refused (double click).
- The order card offers the send instead of a status question; it and the invoice screen say a draft may already be at the provider. Repair and Move to today stay closed; a later write to the row restarts the 15 minutes.
- Until #1412 the row stayed `sending` for good (poll and Hand to GİB: "not sent to a provider"); only cancelling the invoice got out.

## How does a send run? (`erp_edoc_invoice_send()`)
- `edoc_status = 'sending'` is written before the call, so a second click stops at the gate. The code of the account's linked provider card goes along (`erp_edoc_account_link_code()`).
- Failure: `error` + `edoc_error` (the provider's words). Success: `edoc_provider`, `edoc_external_id`, the driver's status, `edoc_sent_at`, and `gib_uuid` / `gib_number` when the answer has them; event `erp.invoice.edoc_changed`.
- Both drivers answer `created`: a draft at the provider, not a document yet. With "Hand the document to GİB in the same step" on (`config.erp_edoc_autosend`, 4.63, default 1, `erp_edoc_autosend()`) the same click runs `erp_edoc_invoice_submit()`; off, the operator looks the draft over and presses Hand to GİB. A failed hand-over leaves `created` with the provider's words; no second draft is ever made.
- `erp_edoc_invoice_submit()` never creates: it refuses `sent` / `accepted` and writes `sent` on success.

## How is the status read?
- `erp_edoc_invoice_poll()` asks the provider that carries the document: a button on the invoice, the order card, the invoice list's bulk action. Nothing polls on its own, nothing processes `erp_edoc_queue`, and neither provider has a webhook.
- A poll may name the provider card the invoice landed on (`party`); an account without a card is then linked to it (`erp_edoc_account_link_from_invoice()`).

## How is a sent document taken back?
- Screens never decide per provider: `erp_edoc_invoice_cancellable($invoice)` answers `ok, manual, reason, url` from the row alone (the driver's `cancellable()`, else a general answer); `erp_edoc_invoice_provider_cancel()` runs the driver's `cancel_invoice()`: delete a draft, cancel through the API, or check the operator did it on the provider's screen (`url`).
- The local cancel (`erp_invoice_cancel()`) runs only after the provider agrees; a refusal leaves both sides as they were. A deleted draft clears `edoc_external_id` (`none`); a cancelled document keeps its numbers (`cancelled`).
- A sent e-Fatura is never cancelled: the buyer answers with a return invoice. Per order: erp-order-cancel-and-refund.md; per provider: erp-parasut-driver.md, erp-isbasi-poll-documents-screens.md.
