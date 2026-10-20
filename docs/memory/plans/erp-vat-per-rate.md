---
name: erp-vat-per-rate
description: Plan (owner's decision 2026-10-21, not started): the ERP takes VAT per rate on the sum of a document's lines, as Paraşüt does; steps, open questions
---

## Why?
- Paraşüt takes each rate's VAT on the sum of that rate's amounts, half up; the ERP, the checkout and the old order path take it line by line (erp-parasut-invoice-lines.md). The driver moves a kurus or so per rate (#961) to keep the total, so Paraşüt's before-VAT and VAT differ from the ERP's books by that much, and a rate total no amount reaches (57.93 at 20%) cannot go to Paraşüt at all.

## What is decided?
- Owner's decision (2026-10-21): the ERP takes the VAT from the rate's total. The driver's shift stays for whatever the ERP still takes per line, and for documents issued before.

## How would it work? (proposed; settle with step 1)
- Per document and rate: VAT = `erp_apply_rate()` on the sum of the rate's amounts after discount. The lines keep their VAT column: the rate's VAT is spread over them by largest remainder, so they add up to it and each is within a kurus of its own rate x amount.
- Issued documents keep their figures; only new ones take the rule (the shift moves nothing on a per-rate document).
- One pure function does the arithmetic (`erp_rate_vat()` in includes/erp/money.php: lines in, line VAT and rate totals out), tested in `tests/erp_money_test.php`; the editor preview (assets/js/erp_invoice_editor.js, `applyRate()` and the totals) follows it.

## What remains?
1. `erp_rate_vat()` and its tests (S).
2. Manual documents: `erp_manual_lines_build()` (includes/erp/invoice_manual.php; invoices, quotes, purchase orders, the API) and the editor preview (M).
3. Returns: `includes/erp/returns.php` takes the returned lines' VAT per rate of the return (M).
4. Orders, after the first open question: the checkout (`update_order_item_taxes()` in includes/fn/ecommerce.php, the header tax in submit_order.php, the cart summary) and `erp_order_lines()` (discount re-tax, tie-back, charge lines from `erp_split_gross()`) (L).
5. The old order path (`parasut_create_invoice()`, includes/fn/parasut.php, from view_order.php) issues the e-document at once and only reports the variance: the driver's shift, or retire it for the ERP path (M).
6. Memory: the rule into erp-money-and-currency.md and erp-discounted-order-vat.md as steps ship; this file goes when done.

## Open questions?
- VAT-inclusive charges and prices (shipping 9.99, surcharge, installment charge): per rate, the amount paid is sometimes no reachable total (57.93). Charge the reachable total at checkout (57.92 or 57.94), or invoice it and book the kurus against the payment as rounding?
- erp-discounted-order-vat.md keeps rate x amount = VAT on every line "because the e-invoice is checked against it". Does GİB (or Paraşüt's UBL) take a line a kurus off it? Unmeasured; the demo company converts no e-document.
- Incoming and purchase invoices (includes/erp/edoc/inbox.php, supplier figures): keep the supplier's VAT as written rather than recompute?
- Withholding and the second tax (ÖTV/ÖİV) per rate too? Paraşüt's withholding over several lines is unmeasured.

## Where to start?
- Step 1, then step 2; read erp-money-and-currency.md and `erp_manual_lines_build()`.
