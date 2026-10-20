---
name: erp-parasut-driver
description: The Paraşüt e-document driver (includes/erp/edoc/parasut.php): request wrapper, buyer card, sales invoice, conversion job, poll, PDF, cancel; what the live API showed
---

## How does an invoice travel through the Paraşüt driver?
- `send_invoice` → refused before any request (each used to fail after the card was opened): a VAT rate Paraşüt does not take (#690, `erp_edoc_parasut_vat_rate_refusal()`); a line without a Pinegrap product while no default product/service is set (#885, `erp_edoc_parasut_product_refusal()`). Then the buyer's contact (the linked card while `GET contacts/{id}` still fits it, else `GET contacts?filter[tax_number]`, else `POST contacts`), then `POST sales_invoices`. Paraşüt's `net_total` ("Genel Toplam", after withholding; `gross_total` is the subtotal) is compared with the ERP's `grand_total` (`erp_edoc_parasut_total_check()`); a record that differs is `DELETE`d. Answers `created`.
- `submit_invoice` → `erp_edoc_parasut_decide_kind()`: final consumer / no number → e-Arşiv; else fresh `e_invoice_inboxes?filter[vkn]`; registered → `POST e_invoices` (rel `invoice`, `scenario`, `to`), else `POST e_archives` (rel `sales_invoice`, `internet_sale` + `shipment`). Job id + 15 min window to `parasut_job_id` / `parasut_job_expires_at`. Answers `sent`.
- `poll` → job (`pending|running|done|error`, `errors[]`) while open, then `GET sales_invoices/{id}?include=active_e_document,contact`; state from `erp_edoc_parasut_edoc_state()`: e-Arşiv `status` bounced|sent|printed|legalized are all signed documents → `accepted` (the word is the message); e-Fatura `waiting`→sent, `failed`→error, `successful`→accepted, `response_type` rejected→rejected. GİB number: the e-document's `invoice_number`, else series + id.
- `fetch_document` → `GET e_archives|e_invoices/{id}/pdf`; 204 = not drawn yet; 200 → signed `url` (1 h) read once without a token.
- `cancel_invoice` → no e-document: `DELETE sales_invoices/{id}`; e-Arşiv: `DELETE sales_invoices/{id}/cancel` while `cancellable_until` has not passed; e-Fatura: refused (return invoice).

## How are withholding, exemption and the order sent?
- Withholding is per line: `details[].vat_withholding_rate`; the code goes in the e-document request as `vat_withholding_params[{detail_id, vat_withholding_code}]` with Paraşüt's line ids (`GET sales_invoices/{id}?include=details`, `erp_edoc_parasut_detail_ids()`, same order as the ERP lines). Lines with different rates share one document.
- Exemption: one code per document (`vat_exemption_reason_code`); 250/350 need `vat_exemption_reason` in words, from the invoice note (refused without one).
- `order_no` is `orders.order_number` (the row id until #912), `order_date` the day; both or none (the conversion is refused with one).

## Rate limit and retries?
- 10 requests per 10 s. `parasut_throttle()` (includes/fn/parasut.php) holds every request of the process back; `erp_edoc_parasut_request()` waits a window and repeats once after a 429, and renews the token once after a 401.

## What did the live API show? (demo company, 2026-10-19)
- Seen: a person's TCKN in `tax_number` (201); only VAT rates 0, 1, 10, 20 (18 → 422); the `include`s, `e_invoice_inboxes` and `DELETE sales_invoices/{id}` (204) as documented. Errors repeat the detail as the title; said once.
- Not seen: no e-document integration in the demo company (`POST e_archives` → 422); conversion, job, PDF and e-Arşiv cancel wait for one.
- Settled on the tevkifat draft (601, 4/10): `vat_withholding_rate` is percent of the VAT (40 on 15.99 → 6.40) and `net_total` is after withholding, so the total check takes `grand_total` only (#630); poll prints Paraşüt's totals (`erp_edoc_parasut_totals()`). Still open: whether `internet_sale.payment_type` takes `DIGER`.
- Capabilities: `einvoice`, `earchive`, `accounts` (#711, erp-parasut-accounts.md). No `inbox` (v4 lists no incoming e-invoices), `ewaybill`, `return`.

## Tests
- `tests/erp_edoc_parasut_test.php`, `tests/parasut_test.php` (throttle).
