---
name: erp-parasut-invoice-lines
description: How an ERP invoice's lines go to Paraşüt, how Paraşüt takes the VAT (per rate, not per line), why some ERP totals are refused or cannot be reached there; shapes tried live
---

## How does a line go to Paraşüt?
- `erp_edoc_parasut_invoice_payload()` (includes/erp/edoc/parasut.php): `quantity`, `unit_price` (VAT-exclusive, kurus as two decimals), `vat_rate`, `description`, the product (erp-parasut-driver.md) and the withholding rate. Paraşüt takes no VAT amount: it computes the VAT itself.
- Discount: the typed rate as `percentage` when it is the line's only discount, else `amount` = `discount_amount`. A campaign rate and a typed rate apply one after the other (`erp_manual_lines_build()`), which no single rate says; until #945 such a line went with the typed rate alone.

## Why must Paraşüt's total equal the ERP's?
- Its `net_total` must equal `grand_total` (`erp_edoc_parasut_total_check()`), else the record is deleted and nothing is issued. Since #955 the refusal also gives Paraşüt's before-VAT, VAT and withheld totals.

## How does Paraşüt take the VAT? (live, 2026-10-21)
- Per VAT rate, on the sum of that rate's line amounts, half up to the kurus; not line by line. 1.02 + 1.02 at 20%: Paraşüt 0.41 (2.04 x 20%), the ERP 0.20 + 0.20. 12.45 at 10% and 12.50 at 1% gave 1.25 and 0.13 (half up, not to even). A unit price with four decimals changed nothing.
- The ERP takes it per line (the checkout, `erp_manual_lines_build()`, `erp_order_lines()`; VAT-inclusive prices through `erp_split_gross()`), so a rate's line roundings often miss the rounding of their sum (about one invoice in three with 2-4 lines at 20%).
- At one rate Paraşüt reaches only the totals B + round(B x rate): at 20% one total in six (3 kurus mod 6), at 10% one in eleven, is reached by no lines. Order #1176, 39.95 + 7.99 VAT + shipping 9.99 = 57.93, came back 57.94.

## How does the driver keep the ERP's total? (#961, owner's decision 2026-10-21)
- `erp_edoc_parasut_vat_shift()`: per rate, the amount B' whose VAT brings it to the ERP's amount + VAT (`erp_edoc_parasut_rate_amount()`); the rate's amount at Paraşüt moves by B' - B, 1-3 kurus. Paraşüt's before-VAT and VAT then differ from the ERP's by that much; the total does not.
- Down: discount on the rate's largest line. Up: back off a line's discount, else onto the largest quantity-1 line's price, else a "Rounding difference" line at the rate after every line, on the default product. A moved line's discount goes as `amount`.
- Not moved: a rate with withholding, a gap over two kurus a line, no amount; the total check still guards them.
- A rate total no amount reaches is refused before anything goes to Paraşüt (`erp_edoc_parasut_total_refusal()` in `send_invoice`'s pre-checks), with the totals on either side.
- The ERP taking the VAT per rate itself is planned: plans/erp-vat-per-rate.md.

## Which line shapes were tried live? (demo company, 2026-10-21)
- Equal (one line per rate): 1.5 KGM x 12.34 at 10%, 99.99 at 1%, 2 x 7.77 with 12.5% off at 20% (137.67); 12.45 at 10% + 12.50 at 1% (26.33).
- 3 x 33.33 with campaign 10% then 5% at 20%: 113.99 at Paraşüt against 102.59 before #945, equal after.
- Shifted (#961), all at 20%: 1.02 + 1.02 went 2.03 + 0.41 = 2.44; 1.03 + 1.03 went 2.07 + 0.41 = 2.48; 2 x 0.54 twice, with the rounding line, 2.17 + 0.43 = 2.60. 57.93 was refused with 57.92 and 57.94, nothing sent.

## Tests
- `tests/erp_edoc_parasut_test.php`: `..._invoice_payload_lines_and_header`, `..._vat_shift`, `..._rate_amount`, `..._invoice_payload_vat_shift`, `..._vat_shift_reaches_the_total` (Paraşüt's arithmetic on 4,000 random invoices).
