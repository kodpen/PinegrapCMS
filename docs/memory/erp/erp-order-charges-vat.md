---
name: erp-order-charges-vat
description: Order shipping, surcharge and instalment charge on the invoice (VAT with the goods on each rate's sum) and the checkout's kurus-lower shipping for an out-of-reach rate total (9.99 shows 9.98)
---

## How does an order's invoice tax its charges? (#1211)
- `erp_order_charge_lines()` (includes/erp/order_bridge.php): the shipping, the surcharge and the instalment charge hold their VAT inside, since the checkout adds none (`erp_shipping_taxed()` = `pg_order_charges_taxed()`: `config.erp_shipping_tax`, else untaxed only in a US store). A charge is shared between the goods' rates in proportion to what each rate's goods come to after the discount (`pg_order_charge_shares()`), one line per rate.
- The VAT is taken with the goods of the rate, on the sum of all the rate's lines, as Paraşüt takes it: the charges' net is the amount `erp_rate_amount()` (includes/erp/money.php) finds for the rate's total, less the goods' net; the change from splitting each charge alone (`erp_split_gross()`) goes on the rate's largest charge line. 1.03 (VAT 0.21) and shipping 1.00 at 20%: alone 0.83 + 0.17, VAT 0.38 on 1.86; now 0.84 + 0.16, VAT 0.37 on 1.87, total 2.24 either way.

## Why does the checkout lower a charge?
- At one rate only totals B + round(B x rate) exist: at 20% one total in six (3 kurus off a multiple of 6) has no B. 39.95 + VAT 7.99 + shipping 9.99 = 57.93 (dev order #1176), 100.00 + 20.00 + shipping 39.99 = 159.99 (#758): no invoice of them can go to Paraşüt (the driver refuses them before sending, #961).
- Owner's decision (2026-10-21): the customer never pays more than the page showed and the invoice is the payment, so the kurus comes off the shipping, which shows 9.98 for 9.99 (no line of its own).

## Where is the rule applied?
- `pg_order_charges_reachable($lines, $discount, $charges)` (includes/fn/ecommerce.php): the shipping, then the surcharge, each lowered by the fewest kurus (one in practice) that keep every rate's total reachable. With two or more rates a kurus moves to another rate instead (`pg_order_charge_shares()`, as the invoice shares it), so only single-rate orders are lowered. Gate: `pg_order_charges_settle_rates()` (ERP on, tax on, charges taxed).
- Shown by the express order widget (`pg_express_order_charged_totals()`; its shipping script keeps the charged figure while the methods the page was drawn with stay picked, and moves the total by the shipping's difference), the classic express order and order preview pages (`pg_order_items_charges_reachable()`) and the checkout's legal documents. Charged by `submit_order.php`; when the order is placed the last recipient whose cost covers the kurus is lowered in `ship_tos`, so receipts and `orders.shipping` read 9.98.
- `pg_tax_rate_amount()` is the checkout's copy of `erp_rate_amount()` (the checkout runs without the ERP); `tests/order_charges_test.php` holds them equal. The Paraşüt driver still has its own `erp_edoc_parasut_rate_amount()`.

## What can still miss Paraşüt's figures?
- An instalment charge is the bank's: a rate total it leaves out of reach keeps the split and is refused there (about one instalment order in six at 20%).
- Orders placed before the rule, marketplace and API orders: their charges are not settled.
- A negative-price line is taxed alone at the checkout (`pg_tax_by_rate()`) and grouped at Paraşüt, with or without charges.

## Tests
- `tests/order_charges_test.php`: on 1,500 random orders the invoice made from what the checkout charges takes each rate's VAT on its sum and meets the order's total. Also `erp_order_lines_test.php`, `express_order_totals_test.php`, `legal_documents_test.php`.

Related: docs/memory/erp/erp-parasut-invoice-lines.md, docs/memory/erp/erp-discounted-order-vat.md, docs/memory/plans/erp-vat-per-rate.md
