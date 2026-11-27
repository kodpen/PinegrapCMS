---
name: tax-automatic-census-oss
description: Automatic tax census: which OSS scheme fits a shop, what the return and its records ask, and whether stored orders hold it (country, rate, euro, corrections, evidence)
---

## Which OSS scheme fits a shop?
- Union scheme: a seller established in the EU, for its distance sales of goods and services to consumers in other member states; the EU-wide threshold of EUR 10,000 a year covers TBE services and intra-EU distance sales of goods (https://vat-one-stop-shop.ec.europa.eu/one-stop-shop_en).
- A shop established outside the EU (Turkey): the non-Union scheme for services to EU consumers, the import scheme (IOSS) for goods up to EUR 150, with an EU intermediary. The Council agreed to drop the EUR 150 limit from 1 July 2028 (https://taxation-customs.ec.europa.eu/news/commission-welcomes-council-agreement-removing-eur150-threshold-vat-import-one-stop-shop-2026-11-09_en).

## What does the return ask?
- Per member state of consumption: the taxable amount and VAT at each standard and reduced rate, services and goods apart, goods by the state they are dispatched from; quarterly (Union, non-Union) or monthly (import); due by the end of the next month; a nil return too (https://vat-one-stop-shop.ec.europa.eu/one-stop-shop/declare-and-pay-oss_en).
- In euro, at the ECB rate of the period's last day; a correction of an earlier period goes into a later return, within 3 years (same page).
- Records kept 10 years: the member state of consumption, type of supply, date, VAT, payments and the information used to fix the customer's location (https://vat-one-stop-shop.ec.europa.eu/one-stop-shop/record-keeping-and-audits-oss_en).

## Do the stored orders hold it?
- Country: `orders.billing_country` and `ship_tos.country`; which of them taxed a line is not stored, the rule is the recipient's for a shipped line, else billing (`tax-automatic-census-doors.md`). A counter sale has none.
- Rate: `order_items.tax_rate` since 11.0.59; renewals and imports leave it empty.
- Amount: line taxes are before the order discount, `orders.tax` after; a report must spread the discount as `pg_order_charged_tax()` (includes/fn/ecommerce.php:4260) does. Shipping's tax sits inside `ship_tos.shipping_cost`.
- Euro: amounts are in the base currency; `currency_rates` keeps dated ECB rates read from Frankfurter (includes/fn/currency_rates.php:111, `pg_currency_rate()` :282), so a shop in another currency could convert.
- Goods or services: only `products.shippable` tells them apart.
- Location evidence: the billing country and `orders.ip_address` (data/backups/english_default/sql.sql:754); the card's country is not kept.
- Corrections: refunds are in `order_refunds` and `orders.refunded_amount`, with no link to the period of the sale.
- The ERP VAT report groups by kind and rate only, not by country (includes/erp/vat_report.php:56-57).
