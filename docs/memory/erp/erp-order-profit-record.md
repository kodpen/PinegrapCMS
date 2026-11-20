---
name: erp-order-profit-record
description: Each order's cost rows and net profit (#10626): the three tables of 11.0.250, the calculation in includes/erp/order_profit.php, the resumable pass, what it never writes
---

## Where is an order's profit kept?
- `includes/erp/order_profit.php` (2026.11.0, step 11.0.250). `erp_order_costs`: an order's cost rows, kind (product, payment, marketplace, shipping, other), source, `source_ref`, amount in minor units; unique on (order_id, kind, source_ref), so a rerun adds no row.
- `erp_order_profit`: one row per order: revenue, `cost_product` … `cost_other`, net, margin (percent, one decimal, NULL without revenue), state (`final`, `estimate`, `incomplete`), channel (`web` or `marketplace:<account id>`; the counter is not told apart yet), `basis` (md5 of the order figures it was built from), `stale` (compute again).
- `erp_cost_rates`: typed rates, kind (payment, shipping, marketplace) + `rate_key` (the `orders.payment_method` value, the shipping method id, the marketplace account id), `percent` DECIMAL(6,3) and `fixed` minor units. No screen yet (NP5).

## How is an order computed?
- `erp_order_profit_compute()` reads nothing; `erp_order_profit_input()` reads the figures.
- Product: an invoiced order takes its invoice's `sale` moves (`erp_stock_moves.cost_total`), one row `invoice:<id>`, when every move has a cost; a move of cost 0 sends the order to the lines.
- A line: a bundle by `order_item_components.unit_cost` × quantity per bundle × bundles (a component with cost 0 takes its average, and the row counts as `average`); a plain line by the average cost met first, kept (`item:<order_item_id>`, source `average`) so a later average never rewrites it. No cost: no row, state `incomplete`.
- Payment: the method's rate on `orders.total`; not on a marketplace order (the marketplace collects). Shipping: each recipient's method rate on `orders.shipping`. Marketplace: the listing's `marketplace_product_map.commission` × the line's gross (price × quantity + `tax_total`) as `estimate`, else the account's rate.
- A row recorded by other code (source gateway, label, marketplace) replaces the rate or estimate of its kind; the pass never removes such rows. A writer calls `erp_order_profit_mark($order_id)` so the pass computes the order again.
- Revenue: `total + gift_card_discount − tax − refunded_amount`, `erp_profit_report()`'s order rule less refunds. Margin = net × 100 / revenue, rounded to one decimal.
- State: incomplete beats estimate (a rate or estimate row) beats final.

## How does the pass run?
- `erp_order_profit_job.php`, `pg_cron_jobs()` entry `erp_order_profit_job`, hourly, light lane; the step adds it to `config.job_dispatch`. Budget 60 s a tick (`pg_cron_time_left()`); work left: `pg_cron_due_again()` in a minute.
- Order of work: stale rows, then billable orders (`erp_order_billable_sql()`) with no profit row, then a rolling recheck of computed rows by `basis` (cursor in `config_text` `erp_order_profit_cursor`). An order no longer billable, or deleted, loses its rows.
- Accounting off (`ERP_ENABLED`): the job reads and writes nothing.

## What does it never write?
- Architect (2026-11-20, agent channel message 10189): no receipt, till, bank or account move, no invoice settled, no payout record or date; a read figure becomes the order's cost row only. `tests/erp_order_profit_test.php` checks that the module writes only `erp_order_costs` and `erp_order_profit`.
- `delete_order.php` deletes the order's rows in both order tables; `erp_cost_rates` holds no order rows.

## What is not in it yet?
- A sales return's goods coming back lower no cost: a refund lowers revenue only.
- Real fees (NP2), label prices (NP3), Trendyol deductions (NP4), screens and the ERP access gate on them (NP5): `plans/order-profit-steps.md`.
