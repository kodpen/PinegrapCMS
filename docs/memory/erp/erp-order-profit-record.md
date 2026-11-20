---
name: erp-order-profit-record
description: Each order's cost rows and net profit (#10626): the three tables of 11.0.250, the calculation in includes/erp/order_profit.php, the resumable pass, what it never writes
---

## Where is an order's profit kept?
- `includes/erp/order_profit.php` (2026.11.0, step 11.0.250). `erp_order_costs`: an order's cost rows, kind (product, payment, marketplace, shipping, other), source, `source_ref`, amount; unique on (order_id, kind, source_ref), so a rerun adds no row.
- Architect (2026-11-20, channel message 10292): amounts are minor units in the order's own currency (the unit of `orders.total`); a real fee read in another currency (NP2, NP4) is converted before it is written, only through `includes/erp/money.php` with `round()`.
- `erp_order_profit`: one row per order: revenue, `cost_product` … `cost_other`, net, margin, state (`final`, `estimate`, `incomplete`), channel (`web` or `marketplace:<account id>`; no counter yet), `basis` (md5 of the order figures), `stale`.
- `erp_cost_rates`: typed rates, kind (payment, shipping, marketplace) + `rate_key` (`orders.payment_method`, shipping method id, marketplace account id), `percent` DECIMAL(6,3) and `fixed` minor units.

## How is an order computed?
- Product: an invoiced order takes its invoice's `sale` moves (`erp_stock_moves.cost_total`), one row `invoice:<id>`, when every move has a cost; a move of cost 0 sends the order to the lines.
- A line: a bundle by `order_item_components.unit_cost` × quantity per bundle × bundles (a component of cost 0 takes its average; source `average`); a plain line by the average cost met first, kept (`item:<order_item_id>`, source `average`) so a later average never rewrites it. No cost: no row, state `incomplete`.
- A rate or estimate is one row per kind with `source_ref` '' (architect, message 10292): two never pile up. Payment: the method's rate on `orders.total`, not on a marketplace order. Shipping: the rates of the recipients' methods on `orders.shipping`, added up. Marketplace: per line the listing's `marketplace_product_map.commission` × the line's gross (price × quantity + `tax_total`), else the account's rate; source `estimate` when any line took the listing's rate.
- A row recorded by other code (source gateway, label, marketplace) replaces the rate or estimate of its kind; the pass never removes such rows. A writer calls `erp_order_profit_mark($order_id)` so the pass computes the order again.
- Revenue: `total + gift_card_discount − tax − refunded_amount`, `erp_profit_report()`'s order rule less refunds. Margin: only `erp_order_profit_margin()`, net × 100 / revenue with `round()` to one decimal, NULL when revenue is 0; never written by hand (architect, message 10292).
- State: incomplete beats estimate (a rate or estimate row) beats final.

## How does the pass run?
- `erp_order_profit_job.php` (`pg_cron_jobs()` `erp_order_profit_job`), hourly, light lane, added to `config.job_dispatch` by the step. Budget 60 s a tick (`pg_cron_time_left()`); work left: `pg_cron_due_again()` in a minute.
- Order of work: stale rows, then billable orders (`erp_order_billable_sql()`) with no profit row, then a rolling recheck of computed rows by `basis` (cursor in `config_text` `erp_order_profit_cursor`). An order no longer billable, or deleted, loses its rows.
- Accounting off (`ERP_ENABLED`): the job reads and writes nothing.

## What does it never write?
- Architect (2026-11-20, channel message 10189): no receipt, till, bank or account move, no invoice settled, no payout record or date; a read figure becomes the order's cost row only. A test checks the module writes only its two order tables.
- `delete_order.php` deletes the order's rows in both order tables. `erp_cost_rates` is left alone; a rate may outlive its method or account, harmless (architect, message 10292).

## What is not in it yet?
- A sales return's goods coming back lower no cost: a refund lowers revenue only.
- Real fees, label prices, Trendyol deductions, screens: NP2 to NP5 (`plans/order-profit-steps.md`).
