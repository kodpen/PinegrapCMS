---
name: erp-parasut-invoice-lines
description: How an ERP invoice's lines go to Paraşüt (price, quantity, VAT, discounts) and why Paraşüt's total must equal grand_total; the line shapes tried live
---

## How does a line go to Paraşüt?
- `erp_edoc_parasut_invoice_payload()` (includes/erp/edoc/parasut.php): `quantity`, `unit_price` (VAT-exclusive, kurus as two decimals), `vat_rate`, `description`, the product (erp-parasut-driver.md) and the withholding rate.
- Discount: the typed rate as `percentage` when it is the line's only discount, else `amount` = `discount_amount`. A campaign rate and a typed rate apply one after the other (`erp_manual_lines_build()`), which no single rate says; until #945 such a line went with the typed rate alone.

## Why must Paraşüt's total equal the ERP's?
- Paraşüt computes every line again from what it is sent. Its `net_total` must equal `grand_total` (`erp_edoc_parasut_total_check()`), else the record is deleted and nothing is issued: "Paraşüt computed X for the invoice, the ERP Y".
- So a line whose figures Paraşüt cannot compute back the same way cannot go there.

## Which line shapes were tried live? (demo company, 2026-10-21)
- Equal: 1.5 KGM × 12.34 at 10%, 99.99 at 1%, 2 × 7.77 with 12.5% off at 20% (137.67).
- 3 × 33.33 with campaign 10% then 5% at 20%: 113.99 at Paraşüt against 102.59 before #945, equal after.

## Tests
- `tests/erp_edoc_parasut_test.php`: `test_erp_edoc_parasut_invoice_payload_lines_and_header`.
