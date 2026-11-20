---
name: stock-planning-features
description: Plan stock-planning, features P1 to P14: the daily record, sales speed, season, lead time, supplier, order quantity, states, dead stock, the screen, the supplier order, bell and API
---

## What is recorded every day?
- (P1) For every tracked product, one row a day: the units sold that day and the stock when the night pass runs. Units come from complete and exported orders of every type (web, marketplace, counter) and ERP sales invoices with `order_id = 0`; a bundle counts as its components; carts and cancelled orders never count.
- (P2) On its first run the pass fills the sold units of the past 365 days from the same sources; their stock stays unknown. Rows older than 400 days are deleted.

## How is the speed read?
- (P3) Units a day: the units of the last 56 days on which the product was in stock at the day's start (a day of unknown stock counts as in stock), divided by those days. Under 14 such days, or no sale in them: "Not enough history", and the product stays on its minimum stock.
- (P4) Season: last year's units in the coming 56 days over last year's units in the 56 days before, between 0.5 and 2, only when both windows sold at least 10 units.

## Who supplies it and how long does it take?
- (P5) The supplier: the account of the product's latest purchase invoice, else of its latest purchase order. None: "No supplier yet".
- (P6) Lead time: the median days from a purchase order's date to its first purchase invoice, over the supplier's last five orders that have one; else the Lead time typed on the supplier's account card; else unknown, and the row has no order-by day.

## What does the plan say per product?
- (P7) Days left (stock over units a day with the season), the run-out day, the order-by day (run-out day less lead time and safety days).
- (P8) The quantity to order: units a day with the season times lead time plus safety days plus the cover days, less stock, less the open quantity on purchase orders; rounded up; never below 0.
- (P9) States: Runs out before goods can come (days left under lead time), Order now (order-by day today or past), Soon (within 14 days), Fine, Not enough history.
- (P10) Dead stock: in stock and no sale for the dead-stock days; its value is units times `avg_cost`; with no cost known the value says so.

## Where is it seen and used?
- (P11) Accounting › Stock Planning: the states as filters, a supplier filter, the columns of P7 to P10, the total value of dead stock, the date the record began. Settings: safety days, cover days, dead-stock days.
- (P12) Per supplier, New purchase order opens the existing form with that supplier and the plan's quantities; the person changes and saves it.
- (P13) The low-stock bell (its switch stays) says for a planned product "runs out in X days, order by Y" once per drop; a product without history keeps today's minimum notice.
- (P14) `GET /erp/stock-plan` with state, supplier and product filters, and its MCP tool.
