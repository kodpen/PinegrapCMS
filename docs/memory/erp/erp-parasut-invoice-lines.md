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
- Per VAT rate, on the sum of that rate's line amounts, half up to the kurus; not line by line. Two lines of 1.02 at 20%: Paraşüt 0.41 (2.04 x 20% = 0.408), the ERP 0.40 (0.20 + 0.20), refused at 2.45 / 2.44. 12.45 at 10% and 12.50 at 1% gave 1.25 and 0.13 (half up, not to even).
- `net_total` is the before-VAT total plus the VAT total, each to the kurus. A unit price with four decimals (8.325) changed nothing (before VAT 48.28).
- The ERP takes the VAT per line (the checkout, `erp_manual_lines_build()`, `erp_order_lines()`), so an invoice is refused whenever a rate's line roundings do not add up to the rounding of their sum. Simulated: about one invoice in three with 2-4 lines at 20% at random prices, one in nine at prices ending .90/.95/.99. Single-line rates always agree.
- At one rate the totals Paraşüt can reach are B + round(B x rate) for a whole-kurus B. At 20% one total in six (3 kurus mod 6) cannot be reached by any line arrangement: an order of 39.95 + 7.99 VAT + shipping 9.99 = 57.93 came back 57.94 (48.28 + 9.66); a rounding line of its own (8.32 + 0.01) did not help either, since the VAT is taken on the rate's sum.
- Open (owner's decision): move a kurus between amount and VAT at Paraşüt when the total is reachable (its VAT then differs from the ERP's by a kurus), take the ERP's VAT per rate, or a rounding line the accountant accepts.

## Which line shapes were tried live? (demo company, 2026-10-21)
- Equal (one line per rate): 1.5 KGM x 12.34 at 10%, 99.99 at 1%, 2 x 7.77 with 12.5% off at 20% (137.67); 12.45 at 10% + 12.50 at 1% (26.33).
- 3 x 33.33 with campaign 10% then 5% at 20%: 113.99 at Paraşüt against 102.59 before #945, equal after.

## Tests
- `tests/erp_edoc_parasut_test.php`: `test_erp_edoc_parasut_invoice_payload_lines_and_header`.
