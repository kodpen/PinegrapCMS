---
name: marketplace-price
description: Price and VAT rate sent to Trendyol and n11 - the checkout's tax-inclusive price (mp_product_price()), the rate inside it (vat_rate), the mapping screen's price column.
---

## Which price is sent to a marketplace?
- The price the checkout charges for one unit, tax included. `products.price` is stored without tax and `update_order_item_taxes()` adds the rate's tax (`pg_tax_apply_rate()`) on the sum of each rate's lines (`pg_tax_by_rate()`); Trendyol's `salePrice`/`listPrice` and n11's prices are what the buyer pays, VAT inside (both work the VAT back out of it).
- Symptom fixed (#979, PR #1138): the stored price went out as the sale price, so a 79.95 product at 18% sold for 79.95 instead of the 94.34 the site collects.

## Where is it built?
- `mp_product_price($product)` in `connectors/base.php`, on the pure `get_tax_inclusive_price($price, $taxable, $product_rate, $zone_rate, $tax_enabled)` in `includes/fn/ecommerce.php` (tests: `tests/tax_inclusive_price_test.php`).
- Tax on, product `taxable`, `get_default_tax_rate()` gives a rate: `price + pg_tax_apply_rate(price, get_effective_tax_rate(tax_rate, zone_rate))` (#1329; on PHP 8.4+ the old `round(rate / 100 x price)` sent 0.50 at 29% as 0.64 and 2.00 at 7.25% as 2.14, a kurus below the checkout; among whole rates also at 35, 41, 47, 57, 58, 69, 70, 82, 94%). Otherwise (tax off, `taxable = 0`, country in no zone) the price itself.
- `mp_product_row()` (stock/price queue) and `mp_listing_items()` (listing create) put it in `price` (minor) and `price_major`. The mapping screen's "Price" column shows the same figure, followed by "VAT included" when it differs from `products.price` (#1166).

## Which zone, and why not the till's?
- `get_tax_inclusive_price()` is the no-buyer twin of `local_sale_line_rate()`. With the ERP on the till takes `erp_store_zone_rate()` (state included); the marketplace always takes the country zone (`get_default_tax_rate()`), the same source as the declared rate, so price and rate agree. On an ERP shop with zones drawn by state the two can differ; that is deliberate.

## What VAT rate is declared?
- The rate inside the price sent. Both rows carry `vat_rate` = `mp_product_vat_rate($product)`, built by the pure `mp_applied_vat_rate($taxable, $product_rate, $zone_rate, $tax_enabled)` from the same inputs as the price (`mp_shop_tax_state()`): `null` when `ECOMMERCE_TAX` is off, `0` when not taxable or the country is in no zone, else `get_effective_tax_rate()`. Tests: `tests/marketplace_vat_rate_test.php`.
- `mp_trendyol_vat_rate()` / `mp_n11_vat_rate()` round a non-null `vat_rate` DOWN to 0/1/10/20. Null or missing (tax off: the stored price is final, VAT inside; hand-built items): the product's own rate, else the zone rate, also for `taxable = 0`.
- Symptom fixed (#1166): tax on, country in no zone: net 100.00 went out with vatRate 20 (seller net 83.33); tax off: a `taxable = 0` product was declared at 0 although its price holds VAT.

## What does the operator do after updating?
- "Send prices" accounts keep the old net price on mapped products until something changes: press "Send all now" once.
- A shop that raised prices by hand to cover VAT now gets VAT twice: lower them first.
- A zone rate or tax switch change does not requeue price accounts (separate work).

## What was not verified?
- No live Trendyol or n11 account: bodies are checked by the connector tests against the documented shapes; Trendyol's TSF being tax-inclusive comes from their docs.
- The n11 order import (reads prices as tax-inclusive) was not changed.

Related: docs/memory/commerce/product-tax-rate.md, docs/memory/commerce/trendyol-stock-price.md, docs/memory/commerce/trendyol-listings.md, docs/memory/commerce/marketplace-listings.md
