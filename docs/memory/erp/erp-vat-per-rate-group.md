---
name: erp-vat-per-rate-group
description: How the ERP takes a document's VAT, withholding and second tax per rate group, as Paraşüt does (owner decision 2026-10-21), where the rule applies, and where the checkout and charge decisions are kept
---

## How does the ERP take a document's taxes? (owner decision 2026-10-21)
- As Paraşüt does (erp-parasut-invoice-lines.md): the VAT once per group of lines with one VAT rate and one withholding rate, on the group's sum (`erp_rate_vat()`, `includes/erp/money.php`, #1001), shared back over the lines by largest remainder (`erp_allocate()`). A line's VAT may be a kurus off its own rate x amount; the lines add up to the group's.
- The withholding is each group's VAT x its rate, summed over the document and rounded once. The second tax is taken per its own rate on the same sums and is never withheld.
- Before, the ERP, the checkout and the old order path took them line by line: the Paraşüt driver moved a kurus or so per rate (#961), a rate with withholding was not moved, and a rate total no amount reaches (57.93 at 20%) could not go at all.

## Where does it apply?
- Typed documents, sales and purchases, and the editor's preview (`erp_manual_lines_build()`, #1004); incoming e-invoices, compared line by line with the supplier's figures before a draft is written (erp-edoc-incoming-invoices.md); returns (#1020).
- An order's invoice: the VAT taken again per rate after a discount (#1055, #1168, erp-discounted-order-vat.md) and the charges with the goods of their rate (#1211, erp-order-charges-vat.md). The checkout takes the same groups (`pg_tax_by_rate()`, #1168) with the ERP's exact rule (`pg_tax_apply_rate()`, #1329, erp-line-total-and-rate-rounding.md); the order screen's old Paraşüt buttons send the ERP's lines (#1268).
- Issued documents keep their figures; the driver's shift stays for them.

## Does the PHP version change which line gets a kurus?
- Not in `erp_allocate()`: equal remainders take the kurus in the lines' own order, the order `pg_tax_spread()` gives the checkout. It used `arsort()`, stable only from PHP 8.0: on PHP 7 the ties of more than 16 lines went to other lines, so an order's line tax and its invoice's line VAT could be a kurus apart (#1371, `tests/erp_money_test.php`).
- Still PHP's own sort: the tie-back of the header tax in `erp_order_lines()` (`order_bridge.php`, which of more than 16 equal lines takes a residual kurus) and the row order of equal totals in the aging and profit reports.

## Where are the checkout and charge decisions? (2026-10-21, 2026-10-22)
- A charge that leaves a rate's total out of reach is a kurus lower (the customer never pays more than shown, and the invoice is the payment), also in stores that send orders with the old Paraşüt buttons, for the instalment charge and for orders placed before the rule: erp-order-charges-vat.md.
