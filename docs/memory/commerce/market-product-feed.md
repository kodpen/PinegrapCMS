---
name: market-product-feed
description: The product feeds priced in a market (PR #12291): the "Prices in" field, config.product_feeds market_id, the fixed or converted price, the offer's saving, the no_market build error
module: product_feeds
---

## How is a feed priced in a market?
- Settings › Ecommerce › Product Feeds, group Products, field "Prices in": "Base currency (CODE)" or an enabled market, "Name (CODE)" (`pg_product_feed_market_choices()`). Drawn only on a site with an enabled market; a save without the field keeps the stored choice.
- Kept as `market_id` (0 for base) in `config.product_feeds` (`pg_product_feed_settings_parse()`), no column. A posted id the card did not offer is 0 (`pg_product_feed_posted_market()`).
- One market for every format: the daily build reads the catalog once and writes all formats from the same items (`product-feed-streaming.md`).

## Which price goes in?
- The currency is the market's (`pg_product_feed_market_for()`: rate from `currencies.exchange_rate`, 1 for a market in the base currency).
- Price: the product's fixed price in the market (`market_prices`, read per chunk), else the base price at the rate rounded by the market's rule (`pg_market_price_minor()`), as the cart charges (`market-prices.md`).
- Sale price: the guest offer's saving (base price − offer price) converted at the rate with `round()`, as the cart converts an offer's amount, taken off the market price; kept only above 0 and below the price (`pg_product_feed_market_prices()`). A percentage offer in the cart may differ from this by a few cents.
- A product whose base price is 0 is still skipped as `no_price`, even with a fixed price.

## What if the market cannot price?
- A chosen market that is no longer enabled, or whose currency has no rate above 0: the build fails with `no_market` ("The market the feeds are priced in is switched off or its currency has no exchange rate."), and the file built before keeps being served. Base prices are never published under the market's currency.

## Tests?
- `tests/market_invoice_feed_test.php`; the feed's other rules: `product-feeds.md`.
