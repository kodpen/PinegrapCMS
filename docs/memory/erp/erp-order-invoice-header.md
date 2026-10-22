---
name: erp-order-invoice-header
description: An order's invoice header: header-only order amounts, charge lines and gift card, subtotal and grand_total, and the total check that refuses a mismatch before a number is spent.
---

## Why are order header amounts not line items?
- `orders.shipping`, `discount`, `surcharge`, `installment_charges` and `gift_card_discount` live only on the header; the `order_items` sum is NOT the order total. `orders.total` includes the card's instalment charge and has the gift card taken off. Invoices and exports handle each separately.
- `discount` also lowers the header VAT (`orders.tax`) while the line tax stays undiscounted, so lines sent as they are over-invoice (erp-discounted-order-vat).
- Shipping, surcharge and instalment charge become lines only through `erp_order_charge_lines()`, after the tie-back (the order's VAT is the goods' VAT alone); how their VAT is taken: erp-order-charges-vat. The instalment line counts in `surcharge_total` (no column of its own); without it every instalment order was refused.
- `erp_shipping_taxed()` (step 4.76, `config.erp_shipping_tax`): `included` taxes these charges, `none` leaves one line at 0, empty lets the store's country decide (untaxed in the US, where sales tax falls on the goods only; taxed elsewhere).
- The gift card is no sale line: `gift_card_total` says how part of the total was paid, and `erp_settle_gift_card()` settles that part in the same transaction.

## When is an order's invoice refused?
- `erp_invoice_from_order_locked()` compares the built `grand_total` with `orders.total + gift_card_discount` (less `totals['installment_left']`, the kuruş a Pay with iyzico instalment was taken lower, #1269) and refuses a mismatch, naming both figures. That check, the account lookup and the period lock run before the transaction and `erp_next_number()`: a refused attempt spends no number.

## What is the header's shape?
- `subtotal` = every line total, charge lines included; `grand_total` = subtotal − discount_total + tax_total. `shipping_total` / `surcharge_total` only say how much of the subtotal those lines are; a reader that adds them again counts shipping twice.

Related: docs/memory/erp/erp-discounted-order-vat.md, docs/memory/erp/erp-order-charges-vat.md, docs/memory/erp/erp-local-sale.md, docs/memory/erp/erp-document-numbering.md
