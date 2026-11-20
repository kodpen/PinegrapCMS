---
name: erp-order-profit-break-even-bell
description: Break-even price per product and channel and the "Sold at a loss" bell (#10736): the formula, the 90 and 30 days, once per product and channel, the state in config_text, where each is shown
---

## How is the break-even price worked out?
- `erp_order_profit_break_even()` (`includes/erp/order_profit.php`), on a product by channel group of `erp_profitability_report()` over the last 90 days (`erp_profitability_break_evens()`), whatever period the screen shows.
- Product cost, shipping and other costs are a cost per unit; payment fees and marketplace deductions a share of revenue. Price = unit cost / (1 − share − margin), rounded up to a whole minor unit (`erp_kurus_up()` in money.php). Without tax, like revenue.
- With a target margin set (`erp_profitability_target()`) a second price reaches it. No sales in the 90 days: no price. Unit cost 0 or below: 0. Share plus margin of 100% or more: null ("Out of reach").
- Shown only: no price is ever changed from it (owner, 2026-11-20, channel message 10092).

## Where is it shown?
- Accounting › Profitability, By product and channel: a "Break-even price" column after Profit margin, the target price under it ("At the target margin: …"); a dash when the product sold nothing on that channel in the 90 days. Not in the workbook.
- `GET /erp/profitability`: `break_even` on each product row (`api/api-erp-order-profit-routes.md`).

## When does the bell ring?
- `erp_alert_profit_loss()` (`includes/erp/alerts.php`), called by `erp_order_profit_job.php` when the pass has caught up (`reason` done), at most once a day.
- Reads the last 30 days (`erp_profitability_report()`, every channel). A product and channel with net profit below zero is announced once; it is forgotten when its net is 0 or more, so a later loss is announced again. One with no sales in the 30 days stays remembered. Lines without a product are left out (`erp_alert_profit_loss_plan()`).
- One bell row a run: action `erp_profit_loss`, title "Sold at a loss"; title column = count, `product_id` = the biggest loss (named when alone), `order_total` = its channel's name. The link opens Profitability on those 30 days. Nobody's own action caused it, so everyone with access is told.
- Kept in `config_text` `erp_profit_loss_alert`: JSON day of the last run and the remembered keys (`product_id|channel`), at most 2,000. No table or column was added.

## Who sees it?
- The bell row: `ERP_ENABLED` and `manage_erp`, as the other ERP rows (`includes/notifications.php`); the API names its subject as the product. With Accounting off or before the tables of 11.0.250 nothing is read or written.

## Where is it tested?
- `tests/erp_order_profit_break_even_test.php`: both prices, no sales, zero and negative cost, out of reach, the once rule and its reset, the kept state.
