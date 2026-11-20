---
name: erp-order-profit-screens
description: Where an order's profit is seen (#10664): the Profit card of view_order.php, the order list's Profit column and "Sold at a loss" filter, the gate, includes/erp/order_profit_view.php
---

## Where is an order's profit seen?
- `view_order.php`: the Profit card, last in the right card column (`pg_order_view_card_column()`), built by `erp_order_profit_card()`: revenue, one line per kind of cost with its sources in words, net profit, margin, and a badge Final (green), Estimate (amber) or Incomplete (red).
- A finished order (`erp_order_billable_statuses()`) the pass has not reached shows "Not computed yet"; any other order shows no card. A row marked `stale` says the general job works it out again.
- `view_orders.php`: a Profit column after Total (net, red below zero, margin under it, `*` when not final; a dash when not computed) and a toolbar filter "Sold at a loss" (`profit=loss`, kept in the screen's session like Status and Order Type).

## Who sees it?
- Architect (2026-11-20, channel message 10189): only with ERP access, the same gate on every surface. `erp_order_profit_shown($user)`: `ERP_ENABLED`, role below 3 or `manage_erp` (`validate_erp_access()`'s rule without its refusal page), and the three tables of 11.0.250 in place.
- Without it nothing of the ERP is loaded or read, the column and the filter are not drawn, and a "loss" filter kept in the session does nothing.

## How is it read?
- Only reads `erp_order_profit` and `erp_order_costs`; nothing on these screens writes them. The list reads the page's profit rows in one query per 1,000 orders (`erp_order_profit_for_orders()`).
- The filter is `erp_order_profit_filter_sql()`, appended to the list's `$sql_type`, so the counts, the figures over the list and the exports follow it as they follow Order Type.
- The stored margin (DECIMAL, a string) prints through `erp_profit_margin_text()`, as the profit report prints it. Amounts are the base currency's minor units (orders are charged in it), shown with `erp_money_out()`.
- The column is not sortable: the sort menu has no profit code.

## Where is it tested?
- `tests/erp_order_profit_view_test.php` (gate, card lines and their sources, filter, margin text, state badge); `tests/commerce_list_driver_test.php` (the column only with access).
