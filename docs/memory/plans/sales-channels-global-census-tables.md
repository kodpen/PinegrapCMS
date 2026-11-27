---
name: sales-channels-global-census-tables
description: Census (2026-11-27): the marketplace tables for listings, category pairing and orders, whether they fit a marketplace with several countries and currencies, where OAuth tokens go
---

## Which tables hold listings, pairings and orders?
- `marketplace_accounts` (`includes/migrations/2026.4.4.php:283`): provider, name, sealed credentials, status, push_stock, push_price, pull_orders, auto_approve, shipment_template, preparing_day.
- `marketplace_product_map` (2026.4.4.php:304): account, product, remote_code, remote_id, status, commission (2026.11.0.php:2777); UNIQUE (account_id, product_id).
- `marketplace_categories` and `marketplace_category_attributes` (2026.4.4.php:450, 465): a cache keyed by provider. `marketplace_category_map` (line 482): an account's pairing of a product group.
- `marketplace_sync_queue` (line 332); `marketplace_order_map` (line 378), UNIQUE (account_id, remote_package_id); `marketplace_remote_products` (2026.11.0.php:2881); the repricer's `marketplace_price_rules`, `_state` (key account and product) and `_log` (lines 8461-8499).

## Do they fit a marketplace with several countries?
- Not under one account. A product has one map row per account: one remote code, one status, one error. Amazon (one authorization a region), eBay (one offer a marketplaceId) and TikTok Shop (one shop cipher a country) list the same product in several countries, each with its own price, state and errors.
- One account a country fits today's rows with no column: the country in the sealed credentials. Its cost: the same connection named in several accounts, and a product paired once a country.
- The category cache is keyed by provider only, while Amazon's product types, eBay's category trees and TikTok's categories change by country. It needs a country key, or one provider key a country (`ebay_de`), which `mp_providers()` would have to repeat.
- Etsy is one shop, one currency: one account fits as n11 does.

## Do they fit several currencies?
- Orders: yes. `orders.currency_code` holds the package's currency and its lines are in that currency's minor units (`commerce/marketplace-order-money.md`).
- Listing prices: no. `mp_product_row()` gives one price in the base currency; a EUR, GBP or USD offer from a TRY shop needs a converted price or a price list. A price per country belongs to the markets plan (`plans/markets-price-lists.md`).
- Repricer: `marketplace_price_state` has no currency; `marketplace_price_log` has `currency`.

## Where do the OAuth tokens go?
- All four connect a shop by OAuth with a refresh token. Memory's rule: tokens live in the connected accounts layer (`api/api-connected-accounts.md`), never a token table; a marketplace account names its connection in the sealed JSON as Merchant Center does.
- That layer is model A: each site registers its own application at the provider. Model B (one application of Kodpen and a broker) is not built, and the owner put it off on 2026-11-21 (`workflow/decisions-made-panel-14.md`).
- The layer has PKCE (`pg_connect_pkce()`, `includes/fn/connect.php:235`), which Etsy requires.
