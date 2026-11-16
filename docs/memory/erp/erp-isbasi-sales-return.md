---
name: erp-isbasi-sales-return
description: The İşbaşı sales return endpoint and body found in the test environment (2026-11-17), why the driver still sends no return, and what Logo was asked.
---

## Does the İşbaşı driver send a sales return?
- No: it declares no `return` capability, so a return stays in the ERP and its e-document is made on İşbaşı's own screen (erp-edoc-send-and-cancel.md).

## What did the test environment show? (2026-11-17)
- The panel saves a return with `PUT /api/v1.0/invoices/saveorupdatereturnsalesinvoice`. The body is the whole invoice with `type: RETURN_SALES_INVOICE`; the link to the original is `salesInvoiceNumber` + `salesInvoiceDate`, and each line's `sourceId` names the original line.
- A line without a product answers 400 "Fatura satırında ürün girilmesi zorunludur."; the full body answers 599 "Could not find controller with id 'LOXFInvoice.jfm'", and the panel's own return save fails with the same error. A return cannot be saved in the test environment.

## What would building it need?
- The test backend to save a return, so the body can be tried end to end; Logo was asked when (`docs/_isbasi_api_notlari.md`, question 2). Then the original invoice's İşbaşı line ids, read with `GET invoices/{id}`.

Related: docs/memory/erp/erp-isbasi-invoice-payload.md, docs/memory/erp/erp-edoc-cancel-paths.md
