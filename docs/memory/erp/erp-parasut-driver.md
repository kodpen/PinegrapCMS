---
name: erp-parasut-driver
description: The Paraşüt e-document driver (includes/erp/edoc/parasut.php): request wrapper, buyer card, sales invoice, conversion job, poll, PDF, cancel; what the live API showed
---

## How does an invoice travel through the Paraşüt driver?
- `send_invoice` → refused before any request: a VAT rate Paraşüt does not take (#690, `erp_edoc_parasut_vat_rate_refusal()`); a line without a Pinegrap product and no default product/service (#885, `erp_edoc_parasut_product_refusal()`). Then the buyer's contact (the linked card while `GET contacts/{id}` fits it, else `GET contacts?filter[tax_number]`, else `POST contacts`), then `POST sales_invoices`. Paraşüt's `net_total` ("Genel Toplam", after withholding) is compared with the ERP's `grand_total` (`erp_edoc_parasut_total_check()`); a record that differs is `DELETE`d. Answers `created`.
- `submit_invoice` → `erp_edoc_parasut_decide_kind()`: final consumer / no number → e-Arşiv; else fresh `e_invoice_inboxes?filter[vkn]`; registered → `POST e_invoices` (rel `invoice`, `scenario`, `to`), else `POST e_archives` (rel `sales_invoice`, `internet_sale` + `shipment`). Job id + 15 min window → `parasut_job_id` / `_expires_at`. Answers `sent`.
- An internet sale's web address and carrier go on e-Arşiv only: `erp_edoc_parasut_document_missing()` skips them for an invoice expected as e-Fatura (`erp_edoc_invoice_expected_kind()`); `submit_invoice` asks once GİB says e-Arşiv (#900).
- `poll` → the job (`pending|running|done|error`, `errors[]`) while open, then `GET sales_invoices/{id}?include=active_e_document,contact`; `erp_edoc_parasut_edoc_state()`: e-Arşiv bounced|sent|printed|legalized (all signed) → `accepted`; e-Fatura `waiting`→sent, `failed`→error, `successful`→accepted, `response_type` rejected→rejected. GİB number: the e-document's `invoice_number`, else series + id.
- `fetch_document` → `GET e_archives|e_invoices/{id}/pdf`; 204 = not drawn yet; 200 → signed `url` (1 h) read once without a token.
- `cancel_invoice` → no e-document: `DELETE sales_invoices/{id}`; e-Arşiv: `DELETE sales_invoices/{id}/cancel` while `cancellable_until` holds; e-Fatura: refused (return invoice).

## How are withholding, exemption and the order sent?
- Withholding is per line (`details[].vat_withholding_rate`); its code goes in the e-document request as `vat_withholding_params[{detail_id, vat_withholding_code}]` with Paraşüt's line ids (`?include=details`, `erp_edoc_parasut_detail_ids()`, ERP line order).
- Exemption: one code per document (`vat_exemption_reason_code`); 250/350 need `vat_exemption_reason` in words, from the invoice note (refused without one).
- `order_no` and `order_date` go together or not at all (the conversion is refused with one); the date is `orders.order_date` as Y-m-d.

## Rate limit and retries?
- 10 requests per 10 s: `parasut_throttle()` (includes/fn/parasut.php) paces the process; `erp_edoc_parasut_request()` repeats once after a 429 (a window later) and after a 401 (new token).

## What did the live API show? (demo company)
- Seen: a person's TCKN in `tax_number` (201); VAT 0, 1, 10, 20 only (18 → 422); the `include`s, `e_invoice_inboxes`, `DELETE sales_invoices/{id}` (204) as documented. Errors repeat the detail as the title.
- Not seen: no e-document integration in the demo company (`POST e_archives`; 2026-10-20 also `POST e_invoices` for a registered VKN → 422); job, PDF and e-Arşiv cancel wait for one.
- 2026-10-20: a free-text line went on the default product/service, its description kept.
- Tevkifat draft (601, 4/10): `vat_withholding_rate` is percent of the VAT (40 on 15.99 → 6.40), `net_total` is after withholding, so the total check takes `grand_total` only (#630); poll prints Paraşüt's totals. Open: whether `internet_sale.payment_type` takes `DIGER`.
- Capabilities: `einvoice`, `earchive`, `accounts` (#711, erp-parasut-accounts.md). No `inbox` (v4 has no incoming e-invoices), `ewaybill`, `return`.

## Tests
- `tests/erp_edoc_parasut_test.php`, `tests/parasut_test.php` (throttle).
