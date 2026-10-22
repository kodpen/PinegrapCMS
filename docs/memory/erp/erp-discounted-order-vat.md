---
name: erp-discounted-order-vat
description: Order VAT at checkout and on the ERP invoice (per rate, again after a discount), which lines they carry, and why header amounts are not line items.
---

## How does the checkout take an order's VAT?
- Per rate, as Paraşüt and the ERP (#1168): `update_order_item_taxes()` taxes each rate once on the charged lines' sum, spread back by largest remainder in line id order (`pg_order_line_taxes()`, `pg_tax_by_rate()`, `pg_tax_spread()` = `erp_allocate()`'s rule). Later-starting recurring and saved-for-later lines are taxed alone. A line's VAT may be a kuruş off its rate (1.02+1.02 at 20%: 0.21+0.20).
- After an order discount VAT is retaken per rate on the rest, like `erp_order_lines()` (`pg_order_charged_tax()`): `submit_order.php`, express order widget, legal docs, legacy preview/receipt pages (`pg_order_items_charged_tax()`). Was `$tax - round($tax*$discount/$subtotal)`: 1.01 at 20% + 1.27 at 10% less 1.00 gave 0.19, invoice 0.18. A line with no stored rate (pre-11.0.59) keeps the header share.
- Live (dev + Paraşüt demo, 2026-10-22): #1178 (14.95+499.95 at 10%, 125.95+45.00 at 20%) VAT 85.68 (per line 85.69); #1179 with a 10% code 77.11 (header share 77.12); Paraşüt equal, unshifted.
- `order_items.tax_total` stays the undiscounted line tax; mixing the two misinvoices.
- Demo data broke the rule: `_seed_demo.php` fixed; `_seed_fix_discount_tax.php` repaired old rows (only error-signature rows; rerunnable).

## How does `erp_order_lines()` build a discounted line?
- Discount spread by line value (`erp_allocate()`), then VAT retaken per rate on the rest (`erp_rate_vat()`, #1168): checkout's figure.
- Then tied to the header VAT: a gap ≤ 1 kuruş per VAT-bearing line (per-line-taxed orders before #1168) is spread over them; wider fails the gate.
- The gate runs BEFORE `erp_next_number()`: rejections use no number.

## Why are order header amounts not line items?
- `orders.shipping`, `discount`, `surcharge`, `gift_card_discount` are header-only; the `order_items` sum is NOT the order total. Invoices/exports handle each separately.
- `discount` also lowers the header VAT (`orders.tax`); line tax stays undiscounted, so lines sent as-is over-invoice.

## Which order lines does the invoice carry?
- Only charged ones: `erp_order_charged_items($order, $items)` (`order_bridge.php`) drops a recurring line (`order_items.recurring_payment_period` set) starting after the **order day** (any recurring line on ClearCommerce), as `submit_order.php` totals (`pg_order_line_charged_today()`). `erp_order_lines()` spreads discount and shipping tax over the rest.
- Why (#1072): `orders.total`, `tax`, `discount` cover charged lines only. With a subscription starting in 14 days the invoice exceeded the order and was refused ("The invoice total … does not match the order total"); the auto invoice reported it on every such order; an uninvoiced order's card refund offered that line.
- Same filter in `erp_invoice_from_order_locked()`, API pre-check `erp_api_order_invoice_check()`, `erp_order_refund_lines()` (also returns dropped rows as `left_out` so `pg_return_erp_line_map()` still maps the return refund, #1156), profit report `erp_profit_order_lines()`; each selects `products.start AS product_start`. All lines unstarted: refused, "nothing to invoice yet"; subscription payments are not invoiced this way.
- Profit report bug (#1157): unstarted line in sales and cost (140.00, header 90.00), subscription-only order in every period.

## When can the filter differ from checkout?
- It reads today's gateway and the line snapshot (`recurring_payment_period`, `recurring_start_date`); `submit_order.php` used `products.recurring` and the submit-time gateway. They differ after an unrecorded ClearCommerce period, a later recurring/start change on the product, or a switch to non-recurring between cart update (which writes `recurring_payment_period`) and submit.
- Still unfiltered (follow-up): old Paraşüt export `parasut_create_invoice()` (`includes/fn/parasut.php` ~1620) sends every non-saved row.
