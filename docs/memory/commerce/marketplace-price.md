---
name: marketplace-price
description: Which price goes to a marketplace (Trendyol, n11) - the checkout's tax-inclusive price built once in mp_product_price(), the VAT rate of an untaxed product, the mapping screen's price column.
---

## Which price is sent to a marketplace?
- The price the shop's checkout charges for one unit, tax included. The shop keeps `products.price` without tax and `update_order_item_taxes()` adds `round(rate / 100 x price)` per line; Trendyol's `salePrice`/`listPrice` and n11's prices are what the buyer pays with the VAT inside (both work the VAT back out of it for invoice and settlement).
- Symptom fixed (#979, PR #1135): the stored price went out as the sale price, so a 79.95 product at 18% sold for 79.95 on the marketplace instead of the 94.34 the site collects; the seller's net was short by the VAT on every price send and every listing.

## Where is it built?
- One place: `mp_product_price($product)` in `connectors/base.php`, on top of the pure `get_tax_inclusive_price($price, $taxable, $product_rate, $zone_rate, $tax_enabled)` in `includes/fn/ecommerce.php` (tests: `tests/tax_inclusive_price_test.php`).
- Formula when `ECOMMERCE_TAX` is on, the product is `taxable` and `get_default_tax_rate()` resolves the shop country to a rate: `price + round(get_effective_tax_rate(products.tax_rate, zone_rate) / 100 x price)`, minor units, the checkout's formula at quantity one. Otherwise (tax off, `taxable = 0`, country in no zone) the price itself: those are the cases where the checkout adds nothing.
- The shop's own country stands in for the buyer's address: a marketplace price is one number for every buyer.
- `mp_product_row()` (stock/price queue) and `mp_listing_items()` (listing create) both select `products.taxable` and put this figure in `price` (minor) and `price_major`; the drivers send `price_major` as it is. The mapping screen's "Price" column shows the same figure, because the column answers "what is it going to send".

## What VAT rate is reported?
- `mp_trendyol_vat_rate()` / `mp_n11_vat_rate()`: the product's own rate, else the zone rate, rounded DOWN to 0/1/10/20. A product with `taxable = 0` is reported at 0: its price carries no tax. An item without the `taxable` key is treated as taxable (items built by hand in tests).

## What was not verified?
- No live Trendyol or n11 account: the bodies are checked by the connector tests against the documented request shapes. Trendyol's TSF being tax-inclusive comes from their docs and commission explanations, not from a real settlement.
- The n11 order import (which reads prices as tax-inclusive) was not changed.

Related: docs/memory/commerce/product-tax-rate.md, docs/memory/commerce/trendyol-stock-price.md, docs/memory/commerce/trendyol-listings.md, docs/memory/commerce/marketplace-listings.md
