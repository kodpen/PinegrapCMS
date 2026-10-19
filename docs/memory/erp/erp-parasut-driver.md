---
name: erp-parasut-driver
description: The Paraşüt e-document driver (includes/erp/edoc/parasut.php): request wrapper, buyer card, sales invoice, conversion job, poll, PDF, cancel; which API readings are still unverified
---

## How does an invoice travel through the Paraşüt driver?
- `send_invoice` → buyer's contact (linked `provider_code`, else `GET contacts?filter[tax_number]`, else `POST contacts`), then `POST sales_invoices`. Paraşüt's `net_total` ("Genel Toplam"; `gross_total` is "Ara toplam", the subtotal before VAT) is compared with the ERP's (`erp_edoc_parasut_total_check()`); a record that differs is `DELETE`d. Answers `created`.
- `submit_invoice` → `erp_edoc_parasut_decide_kind()`: final consumer / no number → e-Arşiv; else fresh `e_invoice_inboxes?filter[vkn]`; registered → `POST e_invoices` (rel `invoice`, `scenario`, `to`), else `POST e_archives` (rel `sales_invoice`, `internet_sale` + `shipment`). Job id + 15 min window to `parasut_job_id` / `parasut_job_expires_at`. Answers `sent`.
- `poll` → job (`pending|running|done|error`, `errors[]`) while open, then `GET sales_invoices/{id}?include=active_e_document,contact`; state from `erp_edoc_parasut_edoc_state()`: e-Arşiv `status` bounced|sent|printed|legalized are all signed documents → `accepted` (the word is the message); e-Fatura `waiting`→sent, `failed`→error, `successful`→accepted, `response_type` rejected→rejected. GİB number: the e-document's `invoice_number`, else series + id.
- `fetch_document` → `GET e_archives|e_invoices/{id}/pdf`; 204 = not drawn yet ("try again in a minute"); 200 → signed `url` (1 h) read once without a token.
- `cancel_invoice` → no e-document: `DELETE sales_invoices/{id}`; e-Arşiv: `DELETE sales_invoices/{id}/cancel` while `cancellable_until` has not passed; e-Fatura: refused (return invoice).

## How are withholding, exemption and the order sent?
- Withholding is per line: `details[].vat_withholding_rate`; the code goes in the e-document request as `vat_withholding_params[{detail_id, vat_withholding_code}]` with Paraşüt's line ids (`GET sales_invoices/{id}?include=details`, `erp_edoc_parasut_detail_ids()`, same order as the ERP lines). Lines with different rates share one document.
- Exemption is one code per document (`vat_exemption_reason_code`); codes 250/350 need `vat_exemption_reason` in words, taken from the invoice note (refused without one).
- `order_no` and `order_date` go together or not at all (Paraşüt refuses the conversion with only one); the date is `orders.order_date` as Y-m-d.

## Rate limit and retries?
- 10 requests per 10 seconds (documentation). `parasut_throttle()` (includes/fn/parasut.php) holds every request of the PHP process back; `erp_edoc_parasut_request()` waits a full window and repeats once after a 429, and renews the token once after a 401.

## Which readings are still unverified on the live API?
- A person's TCKN in `contacts.tax_number`; the scale of `vat_withholding_rate` (percent assumed); whether `net_total` is before or after withholding (both accepted, `erp_edoc_parasut_expected_totals()`); whether `internet_sale.payment_type` takes `DIGER` (not in Paraşüt's enum).
- Capabilities declared: `einvoice`, `earchive`. No `inbox`, `accounts`, `ewaybill`, `return` yet.

## Tests
- `tests/erp_edoc_parasut_test.php`, `tests/parasut_test.php` (throttle).
