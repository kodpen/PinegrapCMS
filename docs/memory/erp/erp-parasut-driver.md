---
name: erp-parasut-driver
description: The Paraşüt e-document driver (includes/erp/edoc/parasut.php): request wrapper, buyer card, sales invoice, conversion job, poll, PDF, cancel; which API readings are still unverified
---

## How does an invoice travel through the Paraşüt driver?
- `send_invoice` → the buyer's contact (linked card's `provider_code`, else `GET contacts?filter[tax_number]`, else `POST contacts`), then `POST sales_invoices` with the lines; Paraşüt's `gross_total` is compared with the ERP's and a record that differs is `DELETE`d again. Answers `created` (`external_id` = sales invoice id, also in `erp_invoices.parasut_invoice_id`).
- `submit_invoice` → `erp_edoc_parasut_decide_kind()`: final consumer / no number → e-Arşiv; else a fresh `e_invoice_inboxes?filter[vkn]` lookup; registered → `POST e_invoices` (`scenario`, `to` = the card's alias when GİB still lists it, else the first); else `POST e_archives` (with `internet_sale` + `shipment` for an internet sale). The `trackable_jobs` id and its 15-minute window go to `parasut_job_id` / `parasut_job_expires_at`, the kind to `edoc_kind`. Answers `sent`.
- `poll` → the job while its window is open (`error` with Paraşüt's words / still `sent` / done), then `GET sales_invoices/{id}?include=active_e_document,contact`: `item_type` cancelled → `cancelled`; no `active_e_document` → `created` (the hand-over button comes back); else the e-document's `uuid` (ETTN) and `status`, read by stems (`erp_edoc_parasut_document_state()`), GİB number = `invoice_series` + `invoice_id`; the e-document id to `parasut_edoc_id`.
- `fetch_document` (pdf only) → `GET e_archives|e_invoices/{id}/pdf` → signed `url` → read once without a token.
- `cancellable` / `cancel_invoice` → what Paraşüt holds decides: no e-document → `DELETE sales_invoices/{id}` (outcome deleted, `parasut_*` cleared); e-Arşiv → `DELETE sales_invoices/{id}/cancel` (outcome cancelled); e-Fatura → refused (return invoice).
- Every call goes through `erp_edoc_parasut_request()`: `erp_edoc_http()` + `erp_edoc_log('parasut', …)`, Bearer from `parasut_get_token()`, one retry after a 401 (`parasut_token_forget()`).

## What must the buyer and the document carry?
- `party_missing`: title, VKN/TCKN with valid check digits (the final consumer's number for a person or the walk-in account without one), tax office for a VKN, address, city, district. A person's one-word name is accepted (Paraşüt has no surname rule).
- `document_missing` (internet sale): web address, carrier title, carrier VKN/TCKN. `tax_office` is a box of the repair form since this driver (`erp_edoc_fix_fields()`).
- Refused before sending: a return or proforma, invoice types other than SATIS/ISTISNA/TEVKIFAT, lines with different withholding rates or codes, zero-rated lines with different exemption codes, a line with no product when no default product/service id is set.

## Which readings are still unverified on the live API?
- A person's TCKN in `contacts.tax_number`; whether `sales_invoice_details` really requires a product; the scale of `vat_withholding_rate`; whether `gross_total` is before or after the withholding (both readings are accepted until the first live document, `erp_edoc_parasut_expected_totals()`); the status words of `e_archives` / `e_invoices`; whether `vat_exemption_reason` text is needed beside the code; the `/cancel` route for a sent e-Arşiv.
- Capabilities declared: `einvoice`, `earchive` only. No `inbox`, `accounts`, `ewaybill`, `return` until their calls are written and seen live.

## Tests
- `tests/erp_edoc_parasut_test.php`: money/currency, expected totals, missing fields, contact attributes, invoice and e-document bodies, status words, error text, cancellable.
