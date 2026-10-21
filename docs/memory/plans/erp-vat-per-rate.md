---
name: erp-vat-per-rate
description: Plan (owner's decisions 2026-10-21; steps 1-2 done, #1001, #1004): the ERP takes VAT and withholding per rate group on a document's lines, as Paraşüt does; steps 3-6 remain
---

## Why?
- Paraşüt takes the VAT on the sum of each group of lines with one VAT rate and one withholding rate, and the withholding on the groups' VAT, rounded once per invoice (erp-parasut-invoice-lines.md); the ERP, the checkout and the old order path take both line by line. The driver moves a kurus or so per rate (#961) to keep the total, so Paraşüt's before-VAT and VAT differ from the ERP's books by that much; a rate total no amount reaches (57.93 at 20%) cannot go at all, and a rate with withholding is not moved (2-4 withheld lines at 20%: four invoices in ten refused).

## What is decided? (owner, 2026-10-21)
- The ERP takes the taxes as Paraşüt does: VAT per (VAT rate, withholding rate) group, the withholding summed over the groups and rounded once; the second tax per its own rate. Issued documents keep their figures; the driver's shift stays for them.
- A line's VAT may be a kurus off its own rate x amount: the group's VAT is spread over its lines by largest remainder, so they add up to it.
- Purchase invoices, typed and from the inbox, take the same rule; the inbox keeps showing a difference from the supplier's figures as a warning.
- Checkout: a VAT-inclusive charge or price whose rate total no amount reaches takes the reachable total a kurus below (57.92 for 57.93); the customer never pays more than the sum shown and the invoice equals the payment.

## How does it work?
- `erp_rate_vat()` (includes/erp/money.php, #1001): lines in (net after discount, VAT rate, withholding rate, second tax rate); line VAT, second tax and withholding, the groups and the totals out. `tests/erp_money_test.php` reproduces Paraşüt's live figures and checks 2,000 random documents.
- Typed documents (#1004): `erp_manual_lines_build()` takes the discounts per line, then the taxes from `erp_rate_vat()`; the editor's `rateTaxes()` / `allocate()` (assets/js/erp_invoice_editor.js) copy it to the kurus, ties to the first line as PHP 8's stable `arsort()`.

## What remains?
1. Done: `erp_rate_vat()` and its tests (#1001).
2. Done: typed documents and the editor preview (#1004).
3. Returns: `includes/erp/returns.php` takes the returned lines' taxes per rate of the return (M).
4. Orders: the checkout (`update_order_item_taxes()` in includes/fn/ecommerce.php, the header tax in submit_order.php, the cart summary; the kurus-below rule above) and `erp_order_lines()` (discount re-tax, tie-back, charge lines from `erp_split_gross()`) (L).
5. The old order buttons (`parasut_create_invoice()`, includes/fn/parasut.php; stores without the ERP or its provider, erp-parasut-legacy-order-buttons.md) issue at once and only report the variance: the driver's shift there too until they go (erp-edocument-leftovers.md) (M).
6. Memory: the rule into erp-money-and-currency.md and erp-discounted-order-vat.md as steps ship; this file goes when done.

## Where to start?
- Step 3: read `erp_return_build()` and how a partial return splits a line (includes/erp/returns.php, erp-return-documents.md).
