---
name: product-tax-class
description: A product's VAT rate class (products.tax_class, 11.0.285): the EU reduced rate it takes under Rates by the buyer's country, which reduced rate the list holds, the Rate Class field
module: tax_eu_rates
---

## What does the class change?
- `products.tax_class` ENUM('standard','reduced','zero') NOT NULL DEFAULT 'standard', step 11.0.285 (`upgrade_2026_11_0_product_tax_class()`); every product stored before is standard, so no charged rate changed. Approved by the owner: `workflow/decisions-made-schema-55.md`.
- Only where the line's rate comes from the EU list (Rates by the buyer's country on, the address in no tax zone, `tax-eu-standard-rate.md`): reduced takes the member state's reduced rate on the order's day, its standard one where it has none (Denmark); zero charges no tax. A tax zone's rate and a product's own rate (`products.tax_rate`, its 0.000 too) win over the class; an untaxable product, Tax off and an exempt order stay at 0.
- `pg_tax_class_line_rate()` (includes/fn/ecommerce.php, pure) is the line's rate; `pg_tax_buyer_list_country()` names the state whose list sets an address's rate ('' under a zone). `update_order_item_taxes()` reads both through `pg_tax_rate_for_buyer_address($country, $state, $date, &$list_country)`, so the checkout and the express order follow; the counter sale, renewals and marketplace imports do not.

## Which reduced rate does the list hold?
- One row a state of kind 'reduced' in `pg_eu_vat_rates()`: the state's lowest reduced rate that applies across its territory (TEDB's REDUCED_RATE outside its REGION category). Super-reduced and parking rates are not taken: Italy's 4%, Spain's 4%, France's 2.1%, Luxembourg's 3% foods and books get the reduced row (5, 10, 5.5, 8). A product in another band (Finland's food 13.5 against its 10) needs its own rate typed.
- Why one band: the class has one reduced value, and the owner's yes covered three classes, not a class per band.
- Read from TEDB's `retrieveVatRates` on 2026-11-27; each row's date is the first month from which TEDB's monthly figures (2016-2026) show its rate without a break, not the day the law set it (TEDB re-filed most categories on 2023-07-01). `tests/tax_class_test.php` holds every state's reduced rate that day.

## Where is it set?
- Product screen (`product_builder.php`), under Taxable beside Tax Rate (%): the Rate Class field (Standard, Reduced, Zero). Drawn and saved only while the column exists and Rates by the buyer's country is on (`pg_product_tax_class_drawn()`); undrawn, the stored class stays. A product copy keeps it (`pg_product_copy_extra_sql()`).
- Not yet in Modify Selected, the CSV import/export or the API's product object.

## What does the change notice do with it?
- `pg_eu_vat_rate_changes()` leaves out a kind the earlier seen list did not hold, so the update that first brings the reduced rows tells nobody. A later change of a reduced row is told; its notice entry reads "EE reduced 9>13 2027-01-01" and the line "EE reduced rate: 9% to 13% from …" (`tax-line-rate-renewal-import.md`).

Related: docs/memory/commerce/tax-eu-standard-rate.md, docs/memory/commerce/product-tax-rate.md
