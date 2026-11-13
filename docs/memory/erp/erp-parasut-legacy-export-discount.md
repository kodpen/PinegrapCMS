---
name: erp-parasut-legacy-export-discount
description: Orders > Export for Paraşüt Selected: the order discount in İNDİRİM TUTARI (column Q) of the product lines, spread by line value, so the invoice is what was paid (#9139)
module: order_list
---

## What does İNDİRİM TUTARI (column Q) hold?
- `edit_orders.php`, case `export_orders_for_parasut`, reads `orders.discount` (an order offer or a code) and spreads it over the order's product lines with `pg_order_parasut_line_discounts()` (`includes/fn/order_list.php`): by line value (unit price × quantity), the remainder by largest remainder (`pg_tax_spread()`), so the shares add up to the discount.
- A line of no value or below zero takes none, and no line takes more than its value (Paraşüt refuses a line below zero); a discount larger than the lines is capped at them.
- With "Product taxes included in the product price" ticked, each share is divided by (1 + rate) like the unit price, since it came off the same VAT-inclusive prices.
- The shipping, surcharge and instalment rows keep Q = 0. The gift card is a means of payment, not a discount, and never goes into Q.

## What was the symptom?
- Every line had Q = 0 whatever the order: a 2,999.00 product with a 10% code (paid 3,238.92 with 20% VAT) was invoiced by Paraşüt at 2,999.00 + 20% = 3,598.80.

## How does it differ from the order screen's Create Invoice?
- Create Invoice builds its lines from `parasut_order_invoice_lines()` (`erp-parasut-legacy-invoice.md`): each line's own charged VAT rate and the checks against the order total. The export still writes the dialog's one VAT rate on every line and checks nothing; only the discount was brought in line.

## Tests
- `tests/parasut_order_export_discount_test.php` (the spread, its edges, the export's use of it); `tests/parasut_export_cells_test.php` (text and number cells).
