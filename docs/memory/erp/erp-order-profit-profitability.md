---
name: erp-order-profit-profitability
description: Accounting › Profitability (#10698): product by channel by period from erp_order_profit, the split of an order over its lines, the workbook, the cost rates and target margin card
---

## Where is it?
- `erp_profitability.php`, ERP menu entry "Profitability" after "Profit and loss"; figures in `includes/erp/profitability.php`. Gate: `validate_erp_access($user)`, so the accountant's read-only right sees it, its inputs disabled and no Save button, and a sent form is refused.
- Without the three tables of 11.0.250 (`erp_order_profit_ready()`) the screen says to run the update and reads nothing.

## What does it show?
- Period picker of the profit and loss screen (`erp_profit_period()`, default this month) and a channel filter (all, Web store, each marketplace account; `erp_profitability_channel_in()`).
- Only `erp_order_profit` rows whose `order_date` is in the period: finished orders the general job computed. Finished orders with no row yet are counted in a note.
- Cards: net sales, costs, net profit, margin (against the target). Tables: By channel, and By product and channel (units, net sales, product cost, payment fees, marketplace deductions, shipping cost, other costs, net profit, margin), most profitable first, 1,000 rows on screen.
- A margin below the target margin is drawn amber (`erp_profitability_below_target()`). A group counts its orders in Estimate and Incomplete state under the product's name.
- At most 20,000 orders a period (`ERP_PROFITABILITY_MAX_ORDERS`), then a note to choose a shorter period.

## How is an order split over products?
- `erp_profitability_split()`: revenue and every non-product cost by each line's gross (price × quantity + `tax_total`, `erp_order_profit_line_gross()`), with `erp_allocate()`, so lines add up to the order exactly.
- Product cost: a line with its own `item:<order_item_id>` cost row keeps it; the rest of the order's product cost goes to the other lines by weight (an invoiced order's single `invoice:<id>` row is spread this way). Lines with no weight share evenly; an order without lines is one row "Lines without a product".
- Margin of a group, a channel or the total: `erp_order_profit_margin()` on the sums, never an average of margins.

## What does the workbook hold?
- `?export=xlsx` writes `erp_profitability_workbook()`: sheet By channel (with the total) and By product and channel (code, product, channel, orders, units, the figures, margin, state). Same period and channel as the screen.

## How are the rates and the target margin kept?
- The settings card "Cost rates and target margin" lists every payment method orders were paid with (distinct `orders.payment_method`, 50 at most), every shipping method and every marketplace account, plus any rate kept for a key no longer listed. A rate is a percent (0-100, three decimals) plus a fixed amount; both empty deletes the row (`erp_profitability_rate_in()`).
- Target margin: `config_text` `erp_profit_target_margin`, a percent from 0 to 99.9 with one decimal, empty for none (`erp_profitability_target_in()`); no column was added.
- The pass's recheck compares only order figures, never rates, so a saved rate that changed marks the orders it applies to `stale` (`erp_profitability_mark_rate()`): shop orders paid with the method, orders with a recipient on the shipping method, orders of the marketplace account. Nothing else is written.

## Where is it tested?
- `tests/erp_profitability_test.php`: the split, groups and totals, channel names and filter, rate and target input, rate change, below target.
