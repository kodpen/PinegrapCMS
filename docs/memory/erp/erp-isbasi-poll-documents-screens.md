---
name: erp-isbasi-poll-documents-screens
description: İşbaşı poll status mapping, fetch_document pitfalls and the e-Arşiv portal printer, taking a document back, the invoice card and list column, the edoc_changed event, 4.62 schema.
---

## How does `poll` map status?
- `GET invoices/{id}` → `data.eGovernmentInvoice {description, eStatus, isSended, rejectNote, invoiceTypeForEinvoice, …}` + `invoiceNumber`, `isCancelled`, `cancelText`, `firm {id, code, name}` (the card the invoice landed on → `party`).
- `isCancelled` → `cancelled`; a cancel text or reject note → `rejected`; `eStatus === 10` → `accepted` (report dictionary: 1/4/8 running, 10 done); error words in the description → `error`; `eStatus 0` and not sent → `created` (saved, not handed to GİB; reading it as "sent" once hid the operator's last step); otherwise `sent`. Kind: `invoiceTypeForEinvoice` 1 e-Fatura, 2/3 e-Arşiv.
- `GetOutgoingInvoiceDataList` only while the ETTN is unknown: it stays empty until GİB has the document, and names a row by its GİB number in a field called `invoiceId`.

## How does `fetch_document` behave?
- pdf → `DocumentDatawithuuid?uuid=<ETTN>&fileFormat=PDF` (no ETTN → "ask after its status first"); when that is not a PDF, the e-Arşiv portal printer `print-eaportal-invoice-pdf?invoiceId=` (POST, body literally `null`). xml → `DocumentUblData?invoiceId=&type=1`. `data.content` is base64.
- The type returned is not the type asked for: before GİB the PDF address gives `type: "HTML"`, the UBL address a zip. `erp_edoc_isbasi_document_kind()` decides by the bytes (%PDF / PK / `<html` / `<?xml`).

## How is a document taken back? (`cancellable`, `cancel_invoice`)
- What İşbaşı holds is read first. A draft (`eStatus 0`, not sent) is deleted with `DELETE /api/v1.0/invoices?Ids=` (the documentation example's `/deleteInvoice` answers 404).
- A sent e-Arşiv has no cancel call in the API: it is cancelled on İşbaşı's screen (refused there until processed), then confirmed here (`manual`; the panel link is offered on the test host only). A sent e-Fatura cannot be cancelled: the buyer answers with a return.

## What are the screens?
- `edit_erp_invoice.php` `$output_edoc` card (after Lines); POST `erp_action=edoc_send | edoc_submit | edoc_poll | edoc_redate | edoc_redate_send | edoc_fix` → `go()`; `get_erp_edoc_document.php?id=&format=pdf|xml` (PDF and HTML in the browser, others download); the UBL button only for a provider with the `ubl` capability (İşbaşı yes, Paraşüt no: until #1026 it led Paraşüt invoices to an error page). Button text carries the provider name ("Logo İşbaşı sağlayıcısına gönder").
- Invoice list `erp_invoices.php` "e-Belge" column, with the list's bulk send, hand-over and status question, only while a provider is set up and in use: `$edoc_column = erp_edoc_installed() && erp_edoc_in_use()` toggles the header AND the cell together (otherwise the column count shifts); sortable by `data-order="<edoc_status>"`.

## How does the taxpayer query work?
- Query beside the tax number on the account form, `erp_taxpayer_lookup.php`, `erp_edoc_account_check_taxpayer()`: docs/memory/erp/erp-taxpayer-lookup.md.

## What are the event and schema? (4.62)
- Event `erp.invoice.edoc_changed` (`erp_webhook_events`); `erp_event_invoice` carries `edoc {provider, status, gib_number, gib_uuid}` on every invoice event.
- `erp_parasut_log` → `erp_edoc_log` + `provider`, `idx_provider`; `install/index.php get_tables()` lists both names and `erp_edoc_providers`; no other code uses the old name.

Related: docs/memory/erp/erp-isbasi-driver.md
