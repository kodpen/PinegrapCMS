---
name: erp-isbasi-poll-documents-screens
description: Isbasi poll status mapping, fetch_document type pitfalls, e-document screens/buttons, taxpayer query, invoice list column, edoc_changed event, 4.62 schema.
---

## How does `poll` map status?
- `GET invoices/{id}` → `data.eGovernmentInvoice {description, eStatus, isSended, rejectNote, gibCode, invoiceTypeForEinvoice, eInvoiceProfile}` + `data.invoiceNumber`, `isCancelled`, `cancelText`.
- Cancel/reject → `rejected`; `eStatus === 10` → `accepted` (report endpoint dictionary: 1/4/8 in progress, 10 done); error/failed in text → `error`; otherwise `sent`.
- `GetOutgoingInvoiceDataList` is called ONLY if the ETTN is unknown: before the document reaches GİB the list returns empty.

## How does `fetch_document` behave?
- pdf → `DocumentDatawithuuid?uuid=<ETTN>&fileFormat=PDF` (no ETTN → "ask the status first"); xml → `DocumentUblData?invoiceId=&type=1`; `data.content` is base64.
- The returned type is NOT the requested type: for a document not yet at GİB the PDF address returns `type: "HTML"` with an HTML body and the UBL address returns `type: "zip"`. `erp_edoc_isbasi_document_kind()` inspects the bytes (%PDF / PK / `<html` / `<?xml`).

## What are the screens?
- `edit_erp_invoice.php` `$output_edoc` card (after Lines); POST `erp_action=edoc_send|edoc_poll` → `go()`; `get_erp_edoc_document.php?id=&format=pdf|xml` (PDF and HTML in the browser, others download). Button text carries the provider name ("Logo İşbaşı sağlayıcısına gönder").
- Invoice list `erp_invoices.php` "e-Belge" column: `$edoc_column = erp_edoc_installed()` toggles the header AND the cell together (otherwise the column count shifts); sortable by `data-order="<edoc_status>"`.

## How does the taxpayer query work?
- `erp_edoc_account_check_taxpayer($account_id, $tax_number = null)` (service.php) → the active provider's `check_taxpayer`; the answer is written to `erp_accounts.einvoice_user / einvoice_alias / einvoice_aliases (JSON) / einvoice_checked_at` (columns empty since 4.43).
- The number ON SCREEN is queried; if it differs from the stored one nothing is written (`stored` false).
- Screen: `edit_erp_account.php` `erp_action=einvoice_check` (inside the main form, `form="form"`, so unsaved fields survive); status line `erp_account_form_einvoice_state($account)` (Vergi Bilgileri card; says so if never queried). The 5th parameter of `erp_account_form_cards()` is the account row.

## What are the event and schema? (4.62)
- Event `erp.invoice.edoc_changed` (`erp_webhook_events`); `erp_event_invoice` payload carries `edoc {provider, status, gib_number, gib_uuid}` on every invoice event.
- `erp_parasut_log` → `erp_edoc_log` + `provider`, `idx_provider`; `install/index.php get_tables()` lists both names and `erp_edoc_providers`. The name `erp_parasut_log` no longer appears in code.

Related: docs/memory/erp/erp-isbasi-driver.md
