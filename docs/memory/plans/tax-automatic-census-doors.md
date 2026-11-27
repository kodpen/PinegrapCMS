---
name: tax-automatic-census-doors
description: Automatic tax census: how each door (checkout, express order, counter sale, API, renewals, imports) decides a line's tax rate today and what it stores on the order
---

## What is the rule at the checkout and the express order?
- `update_order_item_taxes()` (includes/fn/ecommerce.php:4813): an exempt order (`orders.tax_exempt`, the VIES reverse charge) gets tax 0 and rate 0 on every line; else a line with a recipient takes the zone of its `ship_tos` address (:4896), one without the billing zone (:4863). The UPDATE at :4958 writes `order_items.tax_total` and `tax_rate`.
- The zone is `get_tax_rate_for_address()` (includes/fn/forms.php:3266): the first zone holding the country and state, else the country's first zone; false when the address is in no zone, so no tax.
- The line's rate is `pg_order_line_tax_rate()` (ecommerce.php:3411): 0 with tax off, an untaxable product or no zone; else `get_effective_tax_rate()` (:3377), the product's own `tax_rate` unless NULL, else the zone's. A product's own rate applies in every zone.
- Callers, each only while "Tax" (`ECOMMERCE_TAX`) is on: submit_order.php:757, express_order.php:1129, get_express_order.php:118, get_order_preview.php:84, the cart widget (includes/fn/widgets_cart.php:2382; its preview on `get_default_tax_rate()`, :1123).
- submit_order.php:811-870 sums the lines' tax and takes it again after the order discount (`pg_order_charged_tax()`, ecommerce.php:4260); `pg_order_complete()` (includes/fn/order_completion.php:868) writes `orders.subtotal`, `tax`, `shipping`, `total`.

## What do the counter sale and the API do?
- `local_sale_line_rate()` (includes/local_sale.php:269): the ERP store's zone (`erp_store_zone_rate()`, includes/erp/products.php:157) or `get_default_tax_rate()` (ecommerce.php:3935), then the same `pg_order_line_tax_rate()`.
- `pg_local_order_complete()` (includes/fn/local_order.php:394-433) writes the lines' `tax_total` and `tax_rate` and the order's totals; no `billing_country`.
- `api_orders_create()` (includes/api/resources/orders_create.php:96) is a counter sale over the API: the same rule and writer.

## What do renewals, imports and form fees do?
- A renewal takes the first order's tax as `subscriptions.amount` minus price x quantity (includes/fn/subscription_renewal.php:151): no zone or rate is looked up again. Its line (:652-655) has `tax` and `tax_total` but no `tax_rate`; `tax_exempt` and `vat_id` are not copied (:602).
- A marketplace import writes the connector's per-unit `tax`, no `tax_total`, no `tax_rate` (includes/api/outbound/orders.php:778-786).
- A form fee carries no tax (includes/fn/form_payments.php:190, :239).

## What is stored, and what is not?
- Line: `tax_total` (before the order discount) and `tax_rate` (charged). Order: `tax` (after the discount), `billing_country`, `billing_state`, `ip_address`, `tax_exempt`, `vat_id`, `currency_code` (the currency shown; amounts are in the base currency).
- Not stored: the zone or address that decided a line's rate, a product category for a reduced rate, the card's country, the tax inside shipping (`ship_tos.shipping_cost` is gross).
