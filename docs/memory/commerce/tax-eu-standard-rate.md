---
name: tax-eu-standard-rate
description: Rates by the buyer's country (Site Settings › Commerce, beside Tax): an EU buyer in no tax zone pays their state's standard VAT rate; the dated list, its source, who follows
module: tax_eu_rates
---

## What does "Rates by the buyer's country" do?
- A switch in Site Settings › Commerce › Store, under Prices and tax, between Tax and Allow tax-exempt. Off at first; `config_text` `ecommerce_tax_by_country` '1' on, no row off (`pg_tax_by_country_on()`), saved only when drawn (`ecommerce_tax_by_country_card`).
- With Tax on and the switch on, a line whose address (the recipient's, else the billing one) is in no tax zone and in an EU member state takes that state's standard rate on the order's day. A tax zone still wins, a zone of 0 too; a product's own rate wins (0.000 too); an untaxable product and an exempt order (VIES) stay at 0.
- Its warning note, in the switch's cell, shows while it is on: only for a shop registered for the one-stop shop (OSS); the rates are the shop's to check; a wrong rate is the shop's liability. `data-pg-commerce-shows` on the switch (`pg_commerce_settings_reveal_script()`) toggles it.

## Where is it decided?
- `pg_tax_buyer_zone_rate($zone_rate, $country, $by_country, $date)` (includes/fn/ecommerce.php, beneath `pg_order_line_tax_rate()`, pure): the zone's rate unless it is false and the switch is on, then `pg_eu_vat_rate($country, 'standard', $date)`. The result is the `$zone_rate` that `pg_order_line_tax_rate()` takes, so the product rate, taxable flag and Tax switch apply as with a zone.
- `pg_tax_rate_for_buyer_address($country, $state, $date)` reads the zone and the switch; `update_order_item_taxes()` calls it for the billing address and each recipient after the exempt branch. So the checkout and the express order follow, its widget's live tax totals (`eo_tax_totals`) too.
- Not following: the counter sale and `POST /orders` over the API, which is a counter sale (the store's zone, architect 2026-11-27); the cart's preview of lines not yet taxed (`get_default_tax_rate()`, `cart-tax-estimate.md`); renewals and marketplace imports.

## Where does the rate list come from?
- `pg_eu_vat_rates()` (includes/fn/tax_eu_rates.php): rows of member state (ISO code, GR for Greece), kind ('standard' only), rate, valid from. `pg_eu_vat_rate()` takes the row with the latest start on or before the day; EL is read as GR; false outside the 27, for another kind, a malformed day or a day before the first row.
- Read from the Commission's TEDB service `retrieveVatRates` on 2026-11-27 (situationOn that day, and the range 2016-2026 for the dates), checked against Your Europe's table (last checked 13/07/2026). CC BY 4.0; the Commission takes no liability. TEDB's data starts in 2016, so a state's first row carries 2016-01-01 (NL 2016-07-01, SI 2016-05-20), not the day its law set the rate.
- Changes held as rows: DE 16 (2020-07-01) back to 19 (2021-01-01), IE 21 (2020-09-01) back to 23 (2021-03-01), LU 16 (2023-01-01) back to 17 (2024-01-01), EE 22 (2024-01-01) and 24 (2025-07-01), FI 25.5 (2024-09-01), RO 19 (2017-01-01) and 21 (2025-08-01), SK 23 (2025-01-01).
- A rate change is a new row in a PR, kept oldest first; `tests/tax_eu_rates_test.php` holds every state's rate on 2026-11-27.

## What does it not cover?
- Territories outside the EU VAT area under a member state's code (the Canary Islands, Ceuta, Melilla, Mount Athos) get the state's rate; the shop puts them in a zone (0 if due) to stop it. Their own ISO codes (AX, RE, GP, MQ, GF, YT) are not in the list. Northern Ireland (XI) and Monaco (MC) are not either.
- The reduced rate, a rate class and the OSS report are later steps of `plans/tax-automatic.md` (TX4, TX5).

Related: docs/memory/commerce/product-tax-rate.md, docs/memory/commerce/eu-vat-checkout.md, docs/memory/commerce/counter-sale-tax-switched-off.md
