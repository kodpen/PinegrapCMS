---
name: erp-discounted-order-vat
description: How a discounted order's VAT is computed at checkout vs on the ERP invoice, and why order header amounts are not line items.
---

## How does checkout treat VAT on a discounted order?
- `submit_order.php:851` subtracts the discount from VAT before computing the total: `$tax - round($tax * ($discount / $subtotal))`, because a discount shrinks the tax base.
- `order_items.tax_total` still holds the undiscounted line tax. Any read that mixes the two produces a wrong invoice.
- Demo data did not follow this rule: `_seed_demo.php` was fixed and existing rows repaired by `_seed_fix_discount_tax.php` (only rows with the error signature; safe to rerun).

## How does `erp_order_lines()` build a discounted line?
- VAT is re-taken from the remaining base (`erp_apply_rate($line_total - $pay, $rate)`), not reduced by a share: `rate × base = VAT` must hold per line because e-invoice is checked against it.
- Then the line total is tied to the order header VAT: a difference at rounding scale only (≤1 kuruş per VAT-bearing line) is spread over the lines; a wider one makes the gate reject.
- The gate runs BEFORE `erp_next_number()`, so a rejected attempt consumes no number.

## Why are order header amounts not line items?
- `orders.shipping`, `orders.discount`, `orders.surcharge`, `orders.gift_card_discount` live only on the header; the `order_items` sum is NOT the order total. Any code sending to an invoice or outside must handle each separately.
- `discount` is also subtracted from VAT on the header (`submit_order.php:850`) while `order_items.tax` stays undiscounted, so sending the lines as-is over-invoices.

## Which order lines does the invoice carry?
- Only the lines the checkout charged: `erp_order_charged_items($order, $items)` (`order_bridge.php`) keeps a line unless it is recurring (`order_items.recurring_payment_period` set) and starts after the **order day** (or any recurring line on ClearCommerce), the rule `submit_order.php` totals the order by (`pg_order_line_charged_today()`). Then `erp_order_lines()` spreads the discount and the shipping tax over those alone.
- Why: `orders.total`, `tax` and `discount` cover the charged lines only. With a subscription starting in 14 days in the cart the invoice came to more than the order and was refused ("The invoice total … does not match the order total"); the auto invoice reported it on every such order; the card refund dialog of an uninvoiced order offered the unstarted line (#1072).
- The same filter runs in `erp_invoice_from_order_locked()`, the API pre-check (`erp_api_order_invoice_check()`) and `erp_order_refund_lines()`. An order whose lines were all unstarted is refused with "nothing to invoice yet"; its subscription payments are not invoiced by this path.
