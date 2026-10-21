---
name: erp-discounted-order-vat
description: How the checkout takes an order's VAT (per rate, again per rate after a discount) and the ERP invoice the same, and why order header amounts are not line items.
---

## How does the checkout take an order's VAT?
- Per rate, as Paraşüt and the ERP do (#1168): `update_order_item_taxes()` takes each rate's VAT once on the sum of the lines charged with the order and spreads it back by largest remainder, lines in id order (`pg_order_line_taxes()`, `pg_tax_by_rate()`, `pg_tax_spread()` = `erp_allocate()`'s rule). A recurring line starting later and a line saved for later are taxed alone. A line's VAT can be a kuruş off its rate × amount (1.02 + 1.02 at 20%: 0.21 + 0.20).
- After an order discount the VAT is taken again per rate on what the discount leaves of the lines, shared as `erp_order_lines()` shares it (`pg_order_charged_tax()`): `submit_order.php`, the express order widget, the legal documents, the legacy preview and receipt pages (`pg_order_items_charged_tax()`). It was `$tax - round($tax * $discount / $subtotal)`: 1.01 at 20% + 1.27 at 10% less 1.00 gave 0.19, the invoice 0.18. A line without a stored rate (before 11.0.59) keeps that header share.
- Live (dev, Paraşüt demo, 2026-10-22): #1178, 14.95 + 499.95 at 10% and 125.95 + 45.00 at 20%, VAT 85.68 (per line 85.69); #1179 with a 10% code, 77.11 (header share 77.12). Paraşüt equal, unshifted.
- `order_items.tax_total` still holds the undiscounted line tax. Any read that mixes the two produces a wrong invoice.
- Demo data did not follow this rule: `_seed_demo.php` was fixed and existing rows repaired by `_seed_fix_discount_tax.php` (only rows with the error signature; safe to rerun).

## How does `erp_order_lines()` build a discounted line?
- The discount is spread over the lines by value (`erp_allocate()`), then the VAT is taken again per rate on what is left (`erp_rate_vat()`, #1168): the checkout's figure.
- Then the lines are tied to the order header VAT: a gap of at most a kuruş per VAT-bearing line (orders taxed line by line before #1168) is spread over them; a wider one makes the gate reject.
- The gate runs BEFORE `erp_next_number()`, so a rejected attempt consumes no number.

## Why are order header amounts not line items?
- `orders.shipping`, `orders.discount`, `orders.surcharge`, `orders.gift_card_discount` live only on the header; the `order_items` sum is NOT the order total. Any code sending to an invoice or outside must handle each separately.
- `discount` also lowers the header VAT (`orders.tax`) while the lines' tax stays undiscounted, so sending the lines as-is over-invoices.

## Which order lines does the invoice carry?
- Only the lines the checkout charged: `erp_order_charged_items($order, $items)` (`order_bridge.php`) keeps a line unless it is recurring (`order_items.recurring_payment_period` set) and starts after the **order day** (or any recurring line on ClearCommerce), the rule `submit_order.php` totals the order by (`pg_order_line_charged_today()`). Then `erp_order_lines()` spreads the discount and the shipping tax over those alone.
- Why: `orders.total`, `tax` and `discount` cover the charged lines only. With a subscription starting in 14 days in the cart the invoice came to more than the order and was refused ("The invoice total … does not match the order total"); the auto invoice reported it on every such order; the card refund dialog of an uninvoiced order offered the unstarted line (#1072).
- The same filter runs in `erp_invoice_from_order_locked()`, the API pre-check (`erp_api_order_invoice_check()`), `erp_order_refund_lines()` and the profit report's uninvoiced orders (`erp_profit_order_lines()`, `profit.php`). An order whose lines were all unstarted is refused with "nothing to invoice yet"; its subscription payments are not invoiced by this path.
- The profit report reads `products.start AS product_start` in its batched line query and skips an order with nothing charged, so its line net meets its header figure (`erp_profit_uninvoiced_orders()`: total + gift card - tax). Before, it counted the unstarted subscription line as sales and cost (140.00 instead of 90.00), and a subscription-only order stayed as phantom sales in every period (#1157).
