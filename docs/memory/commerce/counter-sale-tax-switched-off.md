---
name: counter-sale-tax-switched-off
description: With the Commerce tax off a counter sale charges the price and no VAT, as the site's checkout does; one rate rule for both doors, pg_order_line_tax_rate() (#10377)
---

## What does the site charge with the tax switched off?
- The checkout runs `update_order_item_taxes()` only while `ECOMMERCE_TAX == true` (`submit_order.php`, express order, preview, cart widget). With it off a line's `tax_total` stays 0 and `order_items.tax_rate` stays NULL: a 100.00 product is charged 100.00.
- The order's ERP invoice then carries VAT 0 on each line (the line's `tax_total`).

## What does the counter charge?
- The same (PR for #10377). `local_sale_line_rate()` and `update_order_item_taxes()` take a line's rate from one rule, `pg_order_line_tax_rate($tax_on, $taxable, $product_rate, $zone_rate)`: 0 while `pg_tax_switched_on()` is false, for a product that is not taxable, or in no zone (false or 0); else the product's rate, the zone's when NULL.
- `pg_local_order_complete()` step 4 writes `order_items.tax_rate` only while the tax is on, as the checkout.
- Before, the counter added the store zone's VAT on top of the price with the tax off: a 100.00 product was charged 120.00. A zone rate of 0 also taxed at the product's own rate at the counter only.

## Is VAT split out of the price on the invoice?
- No: the site does not do it with the tax off (the price is net and the VAT 0), so the counter does not either. Showing VAT inside a tax-off price would be new behaviour.

Test: `tests/local_sale_site_parity_test.php` (one cart through both doors, tax on and off). Related: docs/memory/erp/erp-local-sale.md, docs/memory/commerce/counter-sale-site-line-stages.md
