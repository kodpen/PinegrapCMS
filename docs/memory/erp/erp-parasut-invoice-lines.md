---
name: erp-parasut-invoice-lines
description: How an ERP invoice's lines go to Paraşüt, how Paraşüt takes VAT and withholding (per group, not per line), why some ERP totals are refused or unreachable there; shapes tried live
---

## How does a line go to Paraşüt?
- `erp_edoc_parasut_invoice_payload()` (includes/erp/edoc/parasut.php): `quantity`, `unit_price` (VAT-exclusive, kurus as two decimals), `vat_rate`, `description`, the product (erp-parasut-driver.md) and the withholding rate. Paraşüt takes no VAT amount: it computes the VAT itself.
- Discount: the typed rate as `percentage` when it is the line's only discount, else `amount` = `discount_amount`. A campaign rate then a typed rate is no single rate (#945).

## Why must Paraşüt's total equal the ERP's?
- Its `net_total` must equal `grand_total` (`erp_edoc_parasut_total_check()`), else the record is deleted and nothing is issued. Since #955 the refusal also gives Paraşüt's before-VAT, VAT and withheld totals.

## How does Paraşüt take the VAT and the withholding? (live, 2026-10-21)
- VAT per group of lines with the same VAT rate and withholding rate, on the group's sum, half up to the kurus; not line by line. 1.02 + 1.02 at 20%: 0.41, the ERP 0.20 + 0.20. 12.45 at 10% and 12.50 at 1%: 1.25 and 0.13 (half up, not to even). Four-decimal unit prices change nothing. A withheld and a plain line at one rate are two groups: 1.07 (601) + 1.07 gave 0.42, not 0.43.
- Withholding: each group's VAT x its rate, summed, rounded once per invoice. 1.05 (601) + 1.10 (603) at 20%: 0.24 (8.4 + 15.4 kurus); 601 at 20% and 10%: 0.13 (8.4 + 4.4); 2 x 1.05 (601): 0.17; the ERP 0.23, 0.12, 0.16. Drafts PGF0020-0026.
- At one rate Paraşüt reaches only B + round(B x rate): at 20% one total in six (3 kurus mod 6), at 10% one in eleven, is unreachable. Order #1176 (39.95 + 7.99 VAT + shipping 9.99 = 57.93) came back 57.94.
- `erp_rate_vat()` (includes/erp/money.php, #1001) does this arithmetic. Typed documents take it since #1004 (`erp_manual_lines_build()`): 1.05 (601) + 1.10 (603) went 2.15 + 0.43 - 0.24 = 2.34 at both (PGF0027); three lines at 20%, 97.06 + 19.41 unshifted (PGF0028).
- Orders take it since #1168 (erp-discounted-order-vat.md): #1178, #1179 met Paraşüt unshifted. Taxed shipping and surcharge still split their VAT alone (`erp_split_gross()`).

## How does the driver keep a per-line total? (#961)
- `erp_edoc_parasut_vat_shift()`: per rate, the amount B' whose VAT brings it to the ERP's amount + VAT (`erp_edoc_parasut_rate_amount()`); the rate's amount at Paraşüt moves by B' - B, 1-3 kurus. Paraşüt's before-VAT and VAT then differ from the ERP's by that much; the total does not.
- Down: discount on the rate's largest line. Up: back off a line's discount, else onto the largest quantity-1 line's price, else a "Rounding difference" line at the rate after every line, on the default product. A moved line's discount goes as `amount`.
- Not moved: a rate with withholding (2-4 withheld lines at 20%: four in ten refused), a gap over two kurus a line, no amount; the total check still guards them.
- A rate total no amount reaches is refused before anything goes to Paraşüt (`erp_edoc_parasut_total_refusal()` in `send_invoice`'s pre-checks), with the totals on either side.

## Which line shapes were tried live? (demo company, 2026-10-21)
- Equal: 1.5 KGM x 12.34 at 10%, 99.99 at 1%, 2 x 7.77 with 12.5% off at 20% (137.67); 12.45 at 10% + 12.50 at 1% (26.33); 3 x 33.33, campaign 10% then 5%, at 20% since #945.
- Shifted (#961), at 20%: 1.02 + 1.02 went 2.03 + 0.41; 1.03 + 1.03, 2.07 + 0.41; 2 x 0.54 twice, with the rounding line, 2.17 + 0.43. 57.93 refused (57.92 / 57.94), nothing sent.

## Tests
- `tests/erp_edoc_parasut_test.php`: `..._invoice_payload_lines_and_header`, `..._vat_shift`, `..._rate_amount`, `..._invoice_payload_vat_shift`, `..._vat_shift_reaches_the_total` (4,000 random invoices).
- `tests/erp_money_test.php` `..._rate_vat_reproduces_the_provider_figures`; `tests/erp_invoice_manual_test.php` `..._reproduces_the_provider_totals`.
