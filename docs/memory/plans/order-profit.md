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
- Shipping: `shipment_labels` has no price; since NP3 a label's price is kept as the order's cost row (`erp/erp-order-profit-label-price.md`); `shipping_methods` holds what the shop charges.
- No profit per order or per channel anywhere; `erp_profit_report()` gives per month and per product.

## What binds it?
- It lives in Accounting: cost is the ERP's. New `erp_*` tables are approved in advance (`workflow/decisions-made-data.md`), each through a 2026.11.0 migration step named in its PR; no column on `orders`, `shipping_methods`, `shipment_labels` or `marketplace_*`.
- Money in integer minor units, `round()`. Revenue counted by one rule with `erp_profit_report()`.
- Trendyol's finance endpoints are read from Trendyol's own document in their step, never guessed (`commerce/trendyol-api-facts.md`); 50 requests per 10 s.
- `includes/fn/order_completion.php` and `cart_action.php` are not entered: the profit pass reads orders after the fact.
- Prices never change; the break-even price is shown only.

## What did the architect decide? (2026-11-20, agent channel message 10189)
- No question for the owner. Marketplace deductions sit inside the phase limit: a figure read becomes the order's cost row only; no `erp_receipts`, till, bank or account move, no invoice settled, no payout record, date or amount, nothing matched to a bank line. With Accounting off the job reads and writes nothing.
- Profit only with ERP access (`validate_erp_access()`), as product cost is seen only in the ERP; no new right or column. The same gate on every surface: order card, list column and filter (not drawn without it), Profitability, workbook, bell, API and MCP.
- The target margin starts empty: break-even only. Margin = net profit / revenue without tax (`erp_profit_report()`'s rule), a percent in a setting.
- The three `erp_*` tables are approved in advance; NP1's worker writes their columns into its PR body and asks the architect before marking it ready.

## What is built?
- Features: `order-profit-features.md` (N1 to N14). Steps: `order-profit-steps.md`. Claim as `[plan:order-profit] <step> — <topic>`.

## What is left out?
- Payout reconciliation, n11 and PayTR real fees (typed rates), repricing, advertising costs. Each is a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the owner sees the order's Profit card and the Profitability screen on dev after NP5 lands.
