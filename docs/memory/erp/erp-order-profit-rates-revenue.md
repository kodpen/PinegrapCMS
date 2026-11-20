---
name: erp-order-profit-rates-revenue
description: How the profit pass works out an order's typed rates (payment, shipping by recipients' part, marketplace) and its revenue less a refund without tax (#10792)
---

## How is each typed rate applied?
- Payment: the method's rate on `orders.total`, not on a marketplace order.
- Shipping: each method's rate on its recipients' part of `orders.shipping`, added up; one method takes the whole charge, several split it by their recipients' `ship_tos.shipping_cost` (`erp_allocate()`, input `shipping_costs`); the fixed part once per method.
- Before #10792 every method's percent was taken on the whole charge, so an order with two methods counted its shipping twice.
- Marketplace: per line the listing's `marketplace_product_map.commission` × the line's gross (price × quantity + `tax_total`), else the account's rate; source `estimate` when any line took the listing's rate.

## How is the revenue worked out?
- `erp_order_profit_revenue()`: `total + gift_card_discount − tax`, `erp_profit_report()`'s order rule, less the refund without its tax.
- `refunded_amount` is money paid back, tax included: it comes off as `refunded_amount` × revenue / (total + gift card), with `round()`, capped at the gross. A full refund leaves 0.
- Before #10792 the refund came off whole, so a fully refunded order showed its tax as a negative revenue and landed in "Sold at a loss".

## Where is it tested?
- `tests/erp_order_profit_test.php`: `test_erp_order_profit_refund`, `_full_refund_leaves_no_revenue`, `_shipping_rate_on_each_methods_part`.
