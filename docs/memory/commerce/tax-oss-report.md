---
name: tax-oss-report
description: The One-Stop Shop Report (Commerce, oss_report.php): order_items.tax_country (11.0.287), lines shown apart, discount, euro at the period's last day, CSV
module: tax_oss_report
---

## Where is it, and who sees it?
- Commerce menu, under the tax zones: "One-Stop Shop Report" (`oss_report.php`), gated as the tax zones (`validate_ecommerce_access()`), found by the panel search ("oss", "tek durak").
- Period: Quarter (Union and non-Union schemes) or Month (import scheme), the last 12 quarters or 24 months; `?range=quarter&period=2026-Q3` or `?range=month&period=2026-07` (`pg_oss_period()`). "CSV" adds `export=csv` (UTF-8 with BOM).

## What does an order line store?
- `order_items.tax_country` CHAR(2) NULL, step 11.0.287 (`upgrade_2026_11_0_order_item_tax_country()`), approved by the owner (`workflow/decisions-made-schema-55.md`).
- `update_order_item_taxes()` writes it with `tax_total` and `tax_rate`: `pg_tax_line_country()` (pure, includes/fn/ecommerce.php) of the address that taxed the line, the recipient's else the billing one. The ISO code (GR for EL) when it is an EU member state, whether a tax zone, a product's own rate or the EU list set the rate; '' with Tax off, an exempt (VIES) order or a non-EU address.
- NULL: every line stored before the step, and lines of the doors that write no buyer's country: counter sale and `POST /orders`, renewals, marketplace imports. No stored line is rewritten.

## What does the report count?
- Completed and exported orders whose `order_date` is in the period; active lines only (`pg_order_item_active_sql()`); a recurring line not charged with the order (`pg_order_line_charged_today()`) is left out.
- Groups: member state, goods or services (`products.shippable`), rate. Taxable amount = line amount less its share of the order discount (`pg_tax_spread()`); VAT = `pg_order_line_charged_taxes()` with `orders.tax`, the invoice's rule. A line without a stored rate gets the rate its tax stands for (`pg_tax_line_rate()`).
- Lines with a stored state are the main table. NULL lines go to "Lines without a stored member state": the recipient's country, else the billing country (the census's rule), if a member state and the order not exempt (`pg_oss_line_country()`). A counter sale has no address, so it shows only if it has a billing country.

## How is it put in euro?
- Each group converted once at the period's last day: `pg_currency_rate('EUR', $to)` (base units per euro, the latest on or before the day), `pg_oss_to_euro()` rounds half away from zero; totals are the sum of converted groups. A euro store converts nothing.
- No rate recorded: the figures stay in the base currency and an amber note says so. The base's minor unit is taken as a hundredth.

## What is not in it?
- Shipping (its tax sits inside `ship_tos.shipping_cost`), refunds and corrections (`order_refunds` has no link to a period: a correction goes into a later return by hand), exempt orders, the shop's own country marked apart (the footer line says domestic sales go into the national return).

Related: docs/memory/commerce/tax-eu-standard-rate.md, docs/memory/commerce/product-tax-class.md, docs/memory/commerce/tax-line-rate-renewal-import.md
