---
name: order-template-installment-row
description: The built-in order receipt and order view layouts (includes/templates/order_receipt.php, view_order.php) print the instalment rows, so their rows add up to the Total
---

## Which rows do the built-in custom-layout order screens print?
- A page on a custom layout that was never edited renders `includes/templates/order_receipt.php` or `view_order.php`. `get_order_receipt.php` and `get_view_order_screen_content.php` fold the instalment charge into `$total` and pass `$number_of_installment` and `$installment_charges_info`; the templates print "Number of Installments" and "Installment Charge" after the surcharge when `$installment_charges_info` is set.
- `tests/order_template_totals_test.php` checks that every figure folded into the Total (discount, tax, shipping, gift card, surcharge, instalment charge) is printed before it.

## What was the symptom?
- An order paid in instalments with a bank charge showed a Total higher than its rows on the receipt page and the my-account order view of a custom layout: the templates had no instalment row. Edited layouts (`data/layouts/<page>.php`) keep their own code.
