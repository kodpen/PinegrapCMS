---
name: erp-discounted-order-vat
description: How an order becomes invoice lines: a discounted order's VAT at checkout vs on the invoice, the tie-back to the order's VAT, shipping/surcharge/installment lines (4.76), the total check.
---

## How does checkout treat VAT on a discounted order?
- `submit_order.php` takes the discount off the VAT once, on the header, before the total: `$tax - round($tax * ($discount / $subtotal))`, because a discount shrinks the tax base.
- `order_items.tax_total` still holds the undiscounted line tax (`order_items.tax` is the unit tax). Order lines copied to an invoice as they are over-invoice; a read that mixes the two gives a wrong invoice.
- Demo data broke this rule once: `_seed_demo.php` was fixed and old rows repaired by `_seed_fix_discount_tax.php` (dev machine scripts, not in the repository; only rows with the error signature, safe to rerun).

## How does `erp_order_lines()` build a discounted line?
- The order discount is spread over the goods lines by value (`erp_allocate()`); each line's VAT is then taken again from what is left (`erp_apply_rate($line_total - $share, $rate)`), not reduced by a share: `rate × base = VAT` must hold per line, because the e-invoice is checked against it. The rate is the legal one (`erp_line_tax_rate()`, erp-return-documents).
- The goods lines are then tied to the order's VAT (`orders.tax`): a rounding gap of about a kuruş per VAT-bearing line is spread over them, largest first; a wider gap is left alone and the total check refuses the invoice.

## Why are order header amounts not line items?
- `orders.shipping`, `discount`, `surcharge`, `installment_charges` and `gift_card_discount` live only on the header; the `order_items` sum is NOT the order total. `orders.total` includes the card's installment charge and has the gift card taken off.
- Shipping, surcharge and installment charge become lines only through `erp_order_charge_lines()`, after the tie-back (the order's VAT is the goods' VAT alone). Checkout adds no tax to them, so the tax lies inside the amount charged and is split out with `erp_split_gross()` at the goods' rate; with goods at several rates the charge is shared by rate in proportion to the goods, one line per rate. Without the installment line every installment order was refused. It counts in `surcharge_total` (no column of its own).
- `erp_shipping_taxed()` (step 4.76, `config.erp_shipping_tax`): `included` taxes these charges, `none` leaves one line at 0, empty lets the store's country decide (untaxed in the US, where sales tax falls on the goods only; taxed elsewhere).
- The gift card is no sale line: `gift_card_total` says how part of the total was paid, and `erp_settle_gift_card()` settles that part in the same transaction.

## When is an order's invoice refused?
- `erp_invoice_from_order_locked()` compares the built `grand_total` with `orders.total + gift_card_discount` and refuses a mismatch, naming both figures. That check, the account lookup and the period lock run before the transaction and `erp_next_number()`: a refused attempt spends no number.
- Header shape: `subtotal` = every line total, charge lines included; `grand_total` = subtotal − discount_total + tax_total. `shipping_total` / `surcharge_total` only say how much of the subtotal those lines are; a reader that adds them again counts shipping twice.

Related: docs/memory/erp/erp-local-sale.md, docs/memory/erp/erp-document-numbering.md
