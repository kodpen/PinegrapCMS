---
name: order-profit
description: Plan (owner 2026-11-20, agent channel poll 164): every order's costs and net profit (product, payment fee, marketplace, shipping), by product and channel, break-even price, bell, API; NP1 to NP7
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 164, message 10092): "Evet, plana girsin": each order's costs from the services' real figures where they give them, else typed rates; net profit and margin on the order and a "sold at a loss" filter; product by channel by period; a break-even price; a bell; API and MCP. Prices never change by themselves.

## What is there today?
- Order money in integer minor units (`subtotal`, `discount`, `shipping`, `surcharge`, `installment_charges`, `gift_card_discount`, `tax`, `total`, `refunded_amount`); `orders.commission` is the affiliate's. Channels as `pg_price_history_channel()` names them.
- Product cost only in the ERP: `erp_product_costs`; an invoiced order's sale moves (`erp_stock_moves`); a bundle's `order_item_components.unit_cost`. A plain line has no cost kept at the sale.
- Payment: no fee read or kept, no rate per method. iyzico's answer object carries its commission fee; Stripe's balance transaction and PayPal's capture breakdown are not read; `pg_payment_answer()` keeps fixed keys.
- Marketplace: imported lines carry price only; `marketplace_product_map.commission` is read once at approval, often NULL; no finance call exists.
- Shipping: `shipment_labels` has no price; `pg_label_answer()` drops a service's fee; `shipping_methods` holds what the shop charges.
- No profit per order or per channel anywhere; `erp_profit_report()` gives per month and per product.

## What binds it?
- It lives in Accounting: cost is the ERP's. New `erp_*` tables are approved in advance (`workflow/decisions-made-data.md`), each through a 2026.11.0 migration step named in its PR; no column on `orders`, `shipping_methods`, `shipment_labels` or `marketplace_*`.
- Money in integer minor units, `round()`. Revenue counted by one rule with `erp_profit_report()`.
- Trendyol's finance endpoints are read from Trendyol's own document in their step, never guessed (`commerce/trendyol-api-facts.md`); 50 requests per 10 s.
- `includes/fn/order_completion.php` and `cart_action.php` are not entered: the profit pass reads orders after the fact.
- Prices never change; the break-even price is shown only.

## What does the architect decide?
- Reading a marketplace's deductions per order next to the phase limit "no iyzico or marketplace settlement (payout) import" (`erp/erp-not-built-on-purpose.md`): the plan reads deductions to cost an order and matches nothing to a payout or bank line.
- Who sees profit: the plan shows it only to people with ERP access (`validate_erp_access()`), not to all order staff.
- The target margin starts empty (break-even only) until the merchant sets one.

## What is built?
- Features: `order-profit-features.md` (N1 to N14). Steps: `order-profit-steps.md`. Claim as `[plan:order-profit] <step> — <topic>`.

## What is left out?
- Payout reconciliation, n11 and PayTR real fees (typed rates), repricing, advertising costs. Each is a later yes.

## Whose look?
- The owner's: the order's Profit card and the Profitability screen (NP5).
