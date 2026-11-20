---
name: order-profit-features
description: Plan order-profit, features N1 to N14: an order's cost rows, product cost, payment fee, marketplace deductions, shipping, revenue, profit, the pass, the screens, break-even, bell, API
---

## What does an order cost?
- (N1) Cost rows in `erp_order_costs`: kind (product, payment, marketplace, shipping, other), amount in minor units, source (gateway, marketplace, label, rate, estimate) and its reference; an order has many.
- (N2) Product cost: the sale moves of the order's ERP invoice when it has one; else the average cost when the profit pass first meets the order, kept so a later cost never rewrites it; a bundle by its components' `unit_cost`. No cost known: the profit says "Incomplete".
- (N3) Payment fee: iyzico's commission fee from its payment answer (direct, 3-D Secure, checkout form), Stripe's balance transaction fee, PayPal's capture fee, through a `fee` key of `pg_payment_answer()`. Else a rate (percent plus fixed) typed per payment method. Instalment charges and the surcharge are revenue.
- (N4) Marketplace: Trendyol's deductions per order from its finance service, by a paged read like `mp_trendyol_claims()`; until read, the listing's commission rate times the line amount as an estimate; n11 by a rate typed per account.
- (N5) Shipping: the price a label service charged when it gives one (Kargonomi, Shippo, Sendcloud: the chosen offer's price through `pg_label_answer()`); else a cost typed per shipping method.
- (N6) Revenue: the same rule as `erp_profit_report()` (total plus the gift card part, less tax), less refunds.

## How is profit kept?
- (N7) `erp_order_profit`, one row per order: revenue, costs by kind, net profit, margin, and a state: Final, Estimate (a rate or estimate in it), Incomplete (no product cost).
- (N8) A general job pass, resumable, computes new and changed orders and reworks an order when a later cost row comes (a deduction read, a refund).

## Where is it seen?
- (N9) The order screen: a Profit card (revenue, each cost with its source, net profit, margin, state), only with ERP access.
- (N10) The order list: a profit column and a "Sold at a loss" filter, only with ERP access.
- (N11) Accounting › Profitability: product by channel (web, counter, each marketplace account) by period: units, revenue, costs by kind, net profit, margin; an Excel export like the profit report's. Settings: the typed rates and the target margin.
- (N12) Break-even price per product and channel: the lowest price with net profit 0 at the last 90 days' fees and costs, and at the target margin when one is set. Shown only.
- (N13) The bell: a product sold at a loss on a channel over the last 30 days, once per product and channel until it turns profitable.
- (N14) `GET /erp/orders/{id}/profit` and `GET /erp/profitability` with product, channel and period filters; their MCP tools.
