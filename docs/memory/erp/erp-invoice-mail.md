---
name: erp-invoice-mail
description: E-mailing an issued ERP invoice (4.97): the attachment, erp_document_mails rows, the automatic send decided at the invoice's announcement and sent after the response, testing on dev.
---

## How does an invoice e-mail go? (`includes/erp/mail.php`, 2026.4.4 step 4.97)
- A short covering e-mail with the PDF attached: the provider's official copy once GİB accepted an e-document (kept once, as the invoice screen keeps it), the store's kept PDF otherwise. At most `ERP_MAIL_MAX_RECIPIENTS` (5) addresses.
- Issued sales documents only (`erp_mail_invoice_refusal()`: no purchase, draft or cancelled one). The account's invoice address (`erp_accounts.invoice_email`) goes first, its main address otherwise.
- Every e-mail is an `erp_document_mails` row, written `queued` and settled `sent` or `failed`: the invoice screen lists them, a failed one can be sent again, one that never finished shows as queued.

## When does the automatic e-mail go? (`config.erp_invoice_mail_auto`)
- One door: `erp_event_invoice()` calls `erp_mail_auto_consider()` on `erp.invoice.created` and `erp.invoice.edoc_changed`. Nothing else sends an invoice on its own.
- `erp_mail_auto_target()` decides: without e-documents at the issue; with e-documents in use (`erp_edoc_in_use()`) only once GİB accepted it, so the customer gets the official copy. Not when the account's `invoice_mail` is off, not without a valid address, never twice (an automatic row or any sent row exists).
- The row is written inside the writer's transaction; `erp_mail_defer()` sends after the response (`register_shutdown_function()`, `fastcgi_finish_request()` where present), when what was committed can be read back. A row whose invoice rolled back is dropped as not sent. The queue empties as it runs, so an invoice written during shutdown still gets its e-mail (erp-auto-invoice.md).

## How is it tested on dev?
- `email()` works, but the owner's dev machine has no mail server (docs/memory/workflow/sandbox-and-environment.md): check the screen, the PDF attachment and the address checks, not the delivery. On a live site no real e-mail is sent without the owner's permission.
- Repeating invoices in issue mode trigger it too (erp-recurring-documents.md).
