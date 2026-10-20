---
name: erp-parasut-driver
description: Paraşüt e-document driver (includes/erp/edoc/parasut.php): requests, buyer card, sales invoice, conversion, poll, PDF, cancel; what the live API showed
---

## How does an invoice travel through the Paraşüt driver?
- `send_invoice` → refused before any request: a VAT rate Paraşüt does not take (#690, `erp_edoc_parasut_vat_rate_refusal()`); a line without a Pinegrap product and no default product/service (#885, `erp_edoc_parasut_product_refusal()`). Then the buyer's contact (the linked card while `GET contacts/{id}` fits it, else `GET contacts?filter[tax_number]`, else `POST contacts`), then `POST sales_invoices`. Paraşüt's `net_total` ("Genel Toplam", after withholding) must equal the ERP's `grand_total` (`erp_edoc_parasut_total_check()`), else the record is `DELETE`d. Answers `created`.
- `submit_invoice` → `erp_edoc_parasut_decide_kind()`: final consumer / no number → e-Arşiv; else fresh `e_invoice_inboxes?filter[vkn]`; registered → `POST e_invoices` (rel `invoice`, `scenario`, `to`), else `POST e_archives` (rel `sales_invoice`, `internet_sale` + `shipment`). Job id + 15 min window → `parasut_job_id` / `_expires_at`. Answers `sent`.
- An internet sale's web address and carrier go on e-Arşiv only: `erp_edoc_parasut_document_missing()` skips them for an invoice expected as e-Fatura (`erp_edoc_invoice_expected_kind()`); `submit_invoice` asks once GİB says e-Arşiv (#900).
- `poll` → the job (`pending|running|done|error`, `errors[]`) while open, then `GET sales_invoices/{id}?include=active_e_document,contact`; `erp_edoc_parasut_edoc_state()`: e-Arşiv bounced|sent|printed|legalized (all signed) → `accepted`; e-Fatura `waiting`→sent, `failed`→error, `successful`→accepted, `response_type` rejected→rejected. GİB number: the e-document's `invoice_number`, else series + id. No e-document → `created`; `erp_edoc_parasut_unconverted_message()` says "again" only after a hand-over (#939).
- `fetch_document` → `GET e_archives|e_invoices/{id}/pdf`; 204 = not drawn yet; 200 → signed `url` (1 h) read once without a token.
- `cancel_invoice` → no e-document: `DELETE sales_invoices/{id}`; e-Arşiv: `DELETE sales_invoices/{id}/cancel` while `cancellable_until` holds; e-Fatura: refused (return invoice).

## How are withholding, exemption and the order sent?
- Withholding is per line (`details[].vat_withholding_rate`); its code goes in the e-document as `vat_withholding_params[{detail_id, vat_withholding_code}]` with Paraşüt's line ids (`?include=details`, `erp_edoc_parasut_detail_ids()`, ERP line order).
- Exemption: one code per document (`vat_exemption_reason_code`); 250/350 need `vat_exemption_reason` in words (the invoice note; refused without).
- `order_no` is `orders.order_number` (the row id until #912), `order_date` the day; both or none (one alone is refused).

## Rate limit and retries?
- 10 requests per 10 s: `parasut_throttle()` (includes/fn/parasut.php) paces each process; `erp_edoc_parasut_request()` retries once after a 429 (next window) or a 401 (new token).

## What did the live API show? (demo company)
- Seen: a person's TCKN in `tax_number` (201); VAT 0, 1, 10, 20 only (18 → 422); `include`s, `e_invoice_inboxes`, `DELETE sales_invoices/{id}` (204) as documented; errors repeat the detail as title.
- Not seen: the demo company has no e-document integration (`POST e_archives`, and `POST e_invoices` for a registered VKN → 422); job, PDF, e-Arşiv cancel wait for one.
- A free-text line goes on the default product with its own description.
- Tevkifat draft (601, 4/10): `vat_withholding_rate` is percent of the VAT (40 on 15.99 → 6.40), `net_total` is after withholding and checked against `grand_total` (#630); poll prints Paraşüt's totals. Open: whether `internet_sale.payment_type` takes `DIGER`.
- Capabilities: `einvoice`, `earchive`, `accounts` (#711, erp-parasut-accounts.md). No `inbox` (v4 has no incoming e-invoices), `ewaybill`, `return`.

## Tests
- `tests/erp_edoc_parasut_test.php`, `tests/parasut_test.php` (throttle).
