---
name: order-profit
description: Order profit (2026.11.0, #10626 to #10792): each order's costs, net profit and margin; the features N1 to N14 with the PR and file of each, what differs from the plan, what was left out
---

## What is it?
- Owner (2026-11-20, agent channel poll 164, message 10092): each order's costs from the services' real figures where they give them, else typed rates; net profit and margin on the order, a "Sold at a loss" filter, product by channel by period, a break-even price, a bell, API and MCP. Prices never change by themselves.
- In Accounting: gated by ERP access on every surface; with Accounting off (`ERP_ENABLED`) nothing is read or written (architect, channel message 10189).

## Which feature is where?
- N1 cost rows, N2 product cost, N6 revenue, N7 profit row, N8 the pass (#10626): `erp-order-profit-record.md`; the typed rates and the revenue rule after #10792: `erp-order-profit-rates-revenue.md`.
- N3 payment fee from iyzico, Stripe and PayPal (#10655): `erp-order-profit-payment-fee.md`.
- N4 Trendyol deductions (#10657): `erp-order-profit-deductions.md`.
- N5 label price as the shipping cost (#10659): `erp-order-profit-label-price.md`.
- N9 the Profit card, N10 the list's column and filter (#10664): `erp-order-profit-screens.md`.
- N11 Accounting › Profitability, its workbook, rates and target margin (#10698): `erp-order-profit-profitability.md`.
- N12 break-even, N13 the bell (#10736): `erp-order-profit-break-even-bell.md`; N14 the API and MCP routes: `api/api-erp-order-profit-routes.md`.

## What does the code do differently from the plan's words?
- No counter channel: the channel is `web` or `marketplace:<account id>` only; a counter sale counts as web.
- `delete_order.php` deletes the order's rows in `erp_order_costs` and `erp_order_profit`; `erp_cost_rates` belongs to no order and stays (architect, message 10292).
- n11 and every other marketplace account: the rate typed per account; the listing's `commission` comes first on any account. PayTR and the classic gateways: the payment method's typed rate.
- The payment rate is taken on `orders.total` of the shop's own orders only; a marketplace order has no payment cost row.
- A payment completed by the service's own message (webhook) writes no fee row; a refund does not lower the fee or the product cost.

## What did the bug hunt #10792 change?
- A refund lowers revenue by its part without tax; a full refund leaves 0.
- A shipping rate is taken on each method's recipients' part of the charge, never twice.
- A cancelled order's row counts nowhere (card, list, filter, Profitability, bell) while it waits for the pass to remove it.
- Only the lines charged with the order (`erp_order_charged_items()`) are costed and share revenue: a subscription line that starts later takes none.
- The order route `GET /erp/orders/{id}/profit` still answers a cancelled order's row until the pass removes it (at most an hour).

## How do the real figures and the books meet?
- Never: a figure read becomes the order's cost row only. No receipt, till, bank or account move, no invoice settled, no payout record; Trendyol's invoice and payout reads are never made (`erp-not-built-on-purpose.md`).

## What was left out?
- Payout reconciliation, n11 and PayTR real fees, repricing (`plans/marketplace-repricer.md` reads these rates), advertising costs. Each is a later yes.

## Where is it tested?
- `tests/erp_order_profit_test.php`, `_label_test.php`, `_view_test.php`, `_break_even_test.php`, `_deductions_test.php`, `_api_test.php`, `erp_profitability_test.php`, and the payment and label drivers' tests on recorded answers. No live payment, label or Trendyol call was run.
