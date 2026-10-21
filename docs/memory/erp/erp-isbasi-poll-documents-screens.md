---
name: erp-isbasi-poll-documents-screens
description: Isbasi poll status mapping, fetch_document type pitfalls, e-document screens/buttons, invoice list column, edoc_changed event, 4.62 schema.
---

## How does `poll` map status?
- `GET invoices/{id}` → `data.eGovernmentInvoice {description, eStatus, isSended, rejectNote, gibCode, invoiceTypeForEinvoice, eInvoiceProfile}` + `data.invoiceNumber`, `isCancelled`, `cancelText`.
- Cancel/reject → `rejected`; `eStatus === 10` → `accepted` (report endpoint dictionary: 1/4/8 in progress, 10 done); error/failed in text → `error`; otherwise `sent`.
- `GetOutgoingInvoiceDataList` is called ONLY if the ETTN is unknown: before the document reaches GİB the list returns empty.

## How does `fetch_document` behave?
- pdf → `DocumentDatawithuuid?uuid=<ETTN>&fileFormat=PDF` (no ETTN → "ask the status first"); xml → `DocumentUblData?invoiceId=&type=1`; `data.content` is base64.
- The returned type is NOT the requested type: for a document not yet at GİB the PDF address returns `type: "HTML"` with an HTML body and the UBL address returns `type: "zip"`. `erp_edoc_isbasi_document_kind()` inspects the bytes (%PDF / PK / `<html` / `<?xml`).

## What are the screens?
- `edit_erp_invoice.php` `$output_edoc` card (after Lines); POST `erp_action=edoc_send|edoc_poll` → `go()`; `get_erp_edoc_document.php?id=&format=pdf|xml` (PDF and HTML in the browser, others download); the UBL button only for a provider with the `ubl` capability (İşbaşı yes, Paraşüt no: until #PR762 it led Paraşüt invoices to an error page). Button text carries the provider name ("Logo İşbaşı sağlayıcısına gönder").
- Invoice list `erp_invoices.php` "e-Belge" column: `$edoc_column = erp_edoc_installed()` toggles the header AND the cell together (otherwise the column count shifts); sortable by `data-order="<edoc_status>"`.

## How does the taxpayer query work?
- Query beside the tax number on the account form, `erp_taxpayer_lookup.php`, `erp_edoc_account_check_taxpayer()`: docs/memory/erp/erp-taxpayer-lookup.md.

## What are the event and schema? (4.62)
- Event `erp.invoice.edoc_changed` (`erp_webhook_events`); `erp_event_invoice` payload carries `edoc {provider, status, gib_number, gib_uuid}` on every invoice event.
- `erp_parasut_log` → `erp_edoc_log` + `provider`, `idx_provider`; `install/index.php get_tables()` lists both names and `erp_edoc_providers`. The name `erp_parasut_log` no longer appears in code.

Related: docs/memory/erp/erp-isbasi-driver.md
