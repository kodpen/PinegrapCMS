---
name: erp-vat-per-rate
description: Plan (owner's decisions 2026-10-21; steps 1-3 and 4a done, #1001, #1004, #1020, #1055): the ERP takes VAT and withholding per rate group on a document's lines, as Paraşüt does; 4b, 4c, 5, 6 remain
---

## Why?
- Paraşüt takes the VAT on the sum of each group of lines with one VAT rate and one withholding rate, and the withholding on the groups' VAT, rounded once per invoice (erp-parasut-invoice-lines.md); the ERP, the checkout and the old order path take both line by line. The driver moves a kurus or so per rate (#961) to keep the total, so Paraşüt's before-VAT and VAT differ from the ERP's books by that much; a rate total no amount reaches (57.93 at 20%) cannot go at all, and a rate with withholding is not moved (2-4 withheld lines at 20%: four invoices in ten refused).

## What is decided? (owner, 2026-10-21)
- The ERP takes the taxes as Paraşüt does: VAT per (VAT rate, withholding rate) group, the withholding summed over the groups and rounded once; the second tax per its own rate. Issued documents keep their figures; the driver's shift stays for them.
- A line's VAT may be a kurus off its own rate x amount: the group's VAT is spread over its lines by largest remainder, so they add up to it.
- Purchase invoices, typed and from the inbox, take the same rule; the inbox keeps showing a difference from the supplier's figures as a warning.
- Checkout: a VAT-inclusive charge or price whose rate total no amount reaches takes the reachable total a kurus below (57.92 for 57.93); the customer never pays more than the sum shown and the invoice equals the payment. The kurus comes off the shipping, which shows 9.98 for 9.99 (no line of its own).
- Order lines keep their rate: `order_items.tax_rate` (11.0.59, #1055).

## How does it work?
- `erp_rate_vat()` (includes/erp/money.php, #1001): lines in (net after discount, VAT rate, withholding rate, second tax rate); line VAT, second tax and withholding, the groups and the totals out. `tests/erp_money_test.php` reproduces Paraşüt's live figures and checks 2,000 random documents.
- Typed documents (#1004): `erp_manual_lines_build()` takes the discounts per line, then the taxes from `erp_rate_vat()`; the editor's `rateTaxes()` / `allocate()` (assets/js/erp_invoice_editor.js) copy it to the kurus, ties to the first line as PHP 8's stable `arsort()`.

## What remains?
1. Done: `erp_rate_vat()` and its tests (#1001).
2. Done: typed documents and the editor preview (#1004).
3. Done: returns, the lines given back in part per group of the return, capped by what is left; the card refund dialog takes the whole from the server (#1020).
4. Orders, claim #1037: a) done, the rate on the line (#1055); b) the checkout's VAT per group (`update_order_item_taxes()`), the discounted header tax (submit_order.php and its copies in the previews and receipts), `erp_order_lines()`, the old buttons' rate check; c) the VAT-inclusive charges and the kurus-below rule, `erp_order_charge_lines()`.
5. The old order buttons (`parasut_create_invoice()`, includes/fn/parasut.php; stores without the ERP or its provider, erp-parasut-legacy-order-buttons.md) issue at once and only report the variance: the driver's shift there too until they go (erp-edocument-leftovers.md) (M).
6. Memory: the rule into erp-money-and-currency.md and erp-discounted-order-vat.md as steps ship; this file goes when done.

## Where to start?
- Step 4b: `update_order_item_taxes()` (includes/fn/ecommerce.php), the header tax in submit_order.php (`$tax * $discount / $subtotal`, copied in get_order_preview.php, get_express_order.php, get_order_receipt*.php, get_view_order_screen_content.php), `erp_order_lines()`, `_parasut_order_vat_rates()`; erp-discounted-order-vat.md.
