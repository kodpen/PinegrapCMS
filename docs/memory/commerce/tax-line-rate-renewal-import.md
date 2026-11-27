---
name: tax-line-rate-renewal-import
description: The rate a subscription renewal and a marketplace import store on their line (order_items.tax_rate, pg_tax_line_rate()), and the notice when an update changes a row of the EU VAT list
module: tax_eu_rates
---

## What rate does a renewal's line store?
- `pg_subscription_renewal_order_write()` (includes/fn/subscription_renewal.php) writes `tax_rate` beside `tax` and `tax_total` while the column is there.
- `pg_subscription_renewal_line_rate()`: the first order's line `tax_rate` when it was stored, since the renewal's tax keeps that line's rate (`pg_subscription_renewal_figures()`); else the rate the renewal's own tax stands for on its subtotal.
- The rate is not looked up again: no zone, no buyer's country. Before, the line had no `tax_rate` (NULL), so the invoice worked the rate back out of the amounts.

## What does a marketplace import store?
- `mp_order_write()` (includes/api/outbound/orders.php) writes per line `tax_total` = the connector's unit `tax` x quantity (what `mp_order_totals()` sums into `orders.tax`) and `tax_rate` from `mp_order_line_tax()`.
- The rate is the one that tax stands for on the line's amount less its discount (`discount_total`, else unit discount x quantity), with a slack of the quantity, as the unit tax was rounded a unit at a time.
- Trendyol and n11 hand `tax` 0 (their price holds the VAT): `tax_total` 0 and `tax_rate` 0, which is what the amounts say; the VAT inside the price is not split out here (`marketplace-price.md`).
- Before, an imported line had no `tax_total` and no `tax_rate`.

## How is a rate worked out from amounts?
- `pg_tax_line_rate($base, $tax, $slack)` (includes/fn/tax_eu_rates.php, pure): the fewest decimal places (0 to 3) whose `pg_tax_apply_rate()` comes within `$slack` minor units of the tax; else the ratio at three places. 0 with no amount or no tax; at most 999.999 (DECIMAL(6,3)). The ERP's `erp_line_tax_rate()` keeps its own rule for lines without a stored rate.

## What tells the administrators that an update changed a rate?
- `pg_eu_vat_rate_notice_run()`, called by the general job (job.php): compares `pg_eu_vat_rates()` with the list it last saw, JSON in `config_text` `tax_eu_rates_seen`, and moves that row with `pg_config_text_claim()`, so of two overlapping runs only one tells.
- It tells only while the shop is on and "Rates by the buyer's country" is on; the first run, and any run with the switch off, only takes the list in, so turning the switch on later brings no old changes.
- `pg_eu_vat_rate_changes($seen, $rows)` (pure): every day a row was added, removed or corrected, with the rate before and after on that day where they differ.
- Told once as: a bell line `tax_rate_change` for role 0 (System module; title holds "EE 22>24 2025-07-01, ..." from `pg_eu_vat_rate_notice_title()`, cut at 256 characters), a queued plain-text mail to every enabled administrator with an address, and a Site Log line.
- Tests: `tests/tax_line_rate_test.php`.

Related: docs/memory/commerce/tax-eu-standard-rate.md, docs/memory/commerce/product-tax-rate.md, docs/memory/commerce/marketplace-order-money.md
