---
name: erp-order-charges-vat
description: Order shipping, surcharge and instalment charge on the invoice (VAT with the goods on each rate's sum); the checkout's and the invoice's kurus lower for an out-of-reach rate total (9.99 shows 9.98)
---

## How does an order's invoice tax its charges? (#1211)
- `erp_order_charge_lines()` (includes/erp/order_bridge.php): the shipping, the surcharge and the instalment charge hold their VAT inside, since the checkout adds none (`erp_shipping_taxed()` = `pg_order_charges_taxed()`: `config.erp_shipping_tax`, else untaxed only in a US store). A charge is shared between the goods' rates in proportion to what each rate's goods come to after the discount (`pg_order_charge_shares()`), one line per rate.
- The VAT is taken with the goods of the rate, on the sum of the rate's lines, as Paraşüt takes it: the charges' net is the amount `erp_rate_amount()` finds for the rate's total, less the goods' net; the change from splitting each charge alone (`erp_split_gross()`) goes on the rate's largest charge line. 1.03 (VAT 0.21) and shipping 1.00 at 20%: 0.84 + 0.16, VAT 0.37 on 1.87, not 0.83 + 0.17.

## Why does the checkout lower a charge?
- At one rate only totals B + round(B x rate) exist: at 20% one total in six (3 kurus off a multiple of 6) has no B. 39.95 + VAT 7.99 + shipping 9.99 = 57.93 (dev order #1176) cannot go to Paraşüt (the driver refuses it before sending, #961).
- Owner (2026-10-21): the customer never pays more than the page showed and the invoice is the payment, so the kurus comes off the shipping, which shows 9.98 for 9.99 (no line of its own).

## Where is the rule applied?
- `pg_order_charges_reachable($lines, $discount, $charges)` (includes/fn/ecommerce.php): the shipping, then the surcharge, each lowered by the fewest kurus that keep every rate's total reachable. With two or more rates a kurus moves to another rate instead (`pg_order_charge_shares()`), so only single-rate orders are lowered. Gate: `pg_order_charges_settle_rates()` (ERP or the order screen's Paraşüt buttons on, tax on, charges taxed).
- Shown by the express order widget (`pg_express_order_charged_totals()`), the classic express order and order preview pages (`pg_order_items_charges_reachable()`) and the checkout's legal documents; charged by `submit_order.php`, which lowers the last recipient whose cost covers the kurus in `ship_tos`, so receipts and `orders.shipping` read 9.98. A recipient's own row on the preview pages still shows 9.99.
- `pg_tax_rate_amount()` is the checkout's copy of `erp_rate_amount()` (`tests/order_charges_test.php` holds them equal).

## And the instalment charge? (#1269, owner 2026-10-22)
- iyzico card form: the card driver takes the quoted charge the fewest kurus lower that keep the rates reachable (`pg_order_installment_charge()`, after the shipping and surcharge as charged; submit_order.php passes `settle_rates`, `charged_lines`, `charges`): 57.92 + 2.05 is charged 59.96, the 3-D Secure row and the order keep 2.04.
- Pay with iyzico sets the amount itself: the order's invoice (`erp_order_lines()` with `$settle_charges`, the ERP writer, its API check, the old Paraşüt buttons) takes the instalment line a kurus lower; `totals['charges_left']` is the kurus left on the customer's account. Refunds of order lines do not ask for it.
- The same goes for the shipping and surcharge of an order placed before the rule (#1274, owner 2026-10-22): #1176's 57.93 is invoiced at 57.92.

## What can still miss Paraşüt's figures?
- Marketplace and API orders are settled only on the invoice (a kurus below the payment).
- A negative-price line never reaches Paraşüt (#1297): erp-negative-price-lines.md.

## Tests
- `tests/order_charges_test.php` (1,500 random orders meet the invoice), `erp_order_lines_test.php` (charges settled on random orders), `payment_iyzico_test.php`, `express_order_totals_test.php`, `legal_documents_test.php`.

Related: docs/memory/erp/erp-parasut-invoice-lines.md, docs/memory/erp/erp-discounted-order-vat.md, docs/memory/erp/erp-vat-per-rate-group.md
