---
name: erp-vat-per-rate
description: Plan (owner's decisions 2026-10-21 and 22; steps 1-6 done): the ERP takes VAT and withholding per rate group on a document's lines, as Paraşüt does; 7 (memory) remains
---

## Why?
- Paraşüt takes the VAT on the sum of each group of lines with one VAT rate and one withholding rate, and the withholding on the groups' VAT, rounded once per invoice (erp-parasut-invoice-lines.md); the ERP, the checkout and the old order path took both line by line. The driver moves a kurus or so per rate (#961) to keep the total; a rate total no amount reaches (57.93 at 20%) cannot go at all, and a rate with withholding is not moved.

## What is decided?
- 2026-10-21: the ERP takes the taxes as Paraşüt does: VAT per (VAT rate, withholding rate) group, the withholding summed over the groups and rounded once; the second tax per its own rate. Issued documents keep their figures; the driver's shift stays for them.
- A line's VAT may be a kurus off its own rate x amount: the group's VAT is spread over its lines by largest remainder.
- Purchase invoices, typed and from the inbox, take the same rule; the inbox warns of a difference from the supplier's figures.
- Checkout: a charge whose rate total no amount reaches is taken a kurus lower (57.92 for 57.93; the shipping shows 9.98 for 9.99, no line of its own); the customer never pays more than shown and the invoice is the payment. Order lines keep their rate (`order_items.tax_rate`, 11.0.59).
- 2026-10-22: that checkout rule also in stores without the ERP that send orders with the order screen's Paraşüt buttons. An instalment charge that leaves a rate's total out of reach: the site's card form (iyzico direct or 3-D Secure) charges it a kurus lower; with Pay with iyzico (iyzico sets the amount) the invoice takes the instalment line a kurus lower than paid.

## How does it work?
- `erp_rate_vat()` (includes/erp/money.php, #1001); typed documents `erp_manual_lines_build()` and the editor (#1004); returns (#1020); the checkout's `pg_tax_by_rate()` / `pg_tax_spread()` copy it (#1168, erp-discounted-order-vat.md); charges with the goods on the rate's sum (#1211) and the instalment charge (#1269), erp-order-charges-vat.md; the old order buttons send the ERP's lines (#1268, erp-parasut-legacy-order-buttons.md).

## What remains?
1-4. Done: #1001, #1004, #1020; orders #1055, #1168, #1211 (claim #1037).
5. Done (#1268, claim #1257): `parasut_create_invoice()` takes `erp_order_lines()` and the driver's payload and shift; the checkout rule in those stores.
6. Done (#1269, claim #1257): the iyzico card driver takes the instalment charge the fewest kurus lower (`pg_order_installment_charge()`); the order's invoice (`erp_order_lines()` `$settle_installment`: the ERP writer, its API check, the old buttons) takes a line that leaves a rate out of reach a kurus lower, `installment_left` stays on the customer's account.
7. Memory: the rule into erp-money-and-currency.md and erp-discounted-order-vat.md; this file goes when done.

## Where to start?
- Step 7: move the lasting decisions above into erp-money-and-currency.md and erp-discounted-order-vat.md (the latter is at its size limit: condense), then delete this file.
- After #1243 (exact `erp_apply_rate()`): `pg_tax_by_rate()` / `pg_tax_rate_amount()` take its rule (float `round()` of a rate like 7.7 differs on PHP 8.4+).
