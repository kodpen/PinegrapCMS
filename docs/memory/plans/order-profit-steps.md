---
name: order-profit-steps
description: Plan order-profit, steps NP1 to NP7: the cost record and the pass, payment fees, label prices, Trendyol deductions, the screens, break-even with bell and API, close-out
---

## Which steps are there?
- NP1 (M) first: the record and the pass. In progress.
  - Migration step in 2026.11.0: `erp_order_costs`, `erp_order_profit`, `erp_cost_rates` (typed rates per payment method, shipping method and marketplace account), idempotent helpers.
  - New: `includes/erp/order_profit.php` (pure calculation, the pass), its `pg_cron_jobs()` entry and script, `tests/erp_order_profit_test.php`.
  - `delete_order.php` deletes the order's rows in the three tables. The columns go into the PR body; the architect is asked in the agent channel before it is marked ready (architect, message 10189).
  - Tests, red first: product cost from invoice moves, then the kept average, then a bundle's components; typed rates; the listing estimate; refunds; revenue equal to `erp_profit_report()`'s for the same orders; a later cost reworks. Features: N1, N2, N6, N7, N8 and the rate part of N3 to N5.
- NP2 (S) after NP1: payment fees.
  - `includes/payments/driver.php` (`pg_payment_answer()`'s `fee`), `iyzico.php`, `stripe.php`, `paypal.php`, `submit_order.php` (the cost row after the payment write). Tests on recorded answers. Feature: N3.
- NP3 (S) after NP1: label prices.
  - `includes/fn/carrier_labels.php` (`pg_label_answer()`'s price), the Kargonomi, Shippo and Sendcloud drivers; the cost row when a label is made, removed when it is cancelled. Feature: N5.
- NP4 (M) after NP1: Trendyol deductions.
  - `includes/api/outbound/connectors/trendyol.php` (a paged finance read; endpoints from Trendyol's document, written into `commerce/trendyol-api-facts.md`), its pass; tests on recorded answers. Feature: N4.
- NP5 (M) after NP1: the screens.
  - `view_order.php` (the Profit card in `pg_order_view_card_column()`), `view_orders.php` and `includes/fn/commerce_list.php` (column, filter), new `erp_profitability.php` with its menu entry, settings card and workbook.
  - No pictures (`workflow/look-pictures-none.md`). Features: N9, N10, N11.
- NP6 (S) after NP5: break-even, bell, API.
  - `includes/erp/order_profit.php`, `includes/erp/alerts.php`, `includes/erp/api.php` and a reader; `php tools/check_api_schema.php`. Features: N12, N13, N14.
- NP7 (S) close-out: every N number checked against the code with its PR; `erp/order-profit.md` written; `erp/erp-profit-report.md` updated; `erp/erp-not-built-on-purpose.md`'s settlement line made exact (no reconciliation; an order's deductions are read for its cost); this plan's three files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- After NP1: NP2, NP3, NP4 and NP5 side by side. `view_order.php` and `view_orders.php` are entered by `plans/product-form-uploads.md` FU4a and FU4b too: never open together.
