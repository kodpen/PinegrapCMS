---
name: sales-channels-global-census
description: Census (2026-11-27) for selling on Amazon, eBay, Etsy and TikTok Shop: what the marketplace connector base gives a new marketplace, what n11 and Trendyol each added, where the census's other files are
---

## What is counted, and where is the rest?
- On `development` at c94bc4d1: the outbound marketplace connectors (`includes/api/outbound/connectors/`), their tables, the order import, and the four marketplaces' own developer pages, read 2026-11-27.
- Tables and their fit: `sales-channels-global-census-tables.md`. Each marketplace still to build: `-amazon.md`, `-tiktok.md`; eBay and Etsy are built (`commerce/marketplace-ebay.md`, `commerce/marketplace-etsy.md`). Pages not read: `-unread.md`. Order, storage and the owner's questions: `-proposal.md`.

## What does the base give a new marketplace?
- A connector is a table of function names from `mp_<key>_connector()` in `connectors/<key>.php`, loaded by `mp_connector()` (base.php:285) from `mp_providers()` (base.php:101). Only `test_credentials` is required; `mp_can()` / `mp_call()` (base.php:322, 330) ask and call the rest, and a missing ability hides its action.
- Abilities the base knows (base.php:20-60): credentials, stock and price, tasks, orders and their stage, line approval, webhooks, shipment and labels, order update, categories and attributes, listings and their status, buybox, the shop's own products.
- An account is a `marketplace_accounts` row whose provider `fields` (text, secret, select) are sealed as JSON (`mp_credentials_encode`, base.php:421): a new provider adds no column.
- Stock and price: each write calls `pg_marketplace_product_changed()`; `mp_enqueue()` (base.php:622) queues only for a provider with `push_stock_price` (line 654); `api_sync_job.php` leases a row 300 s and retries `60*2^n`, 6 tries.
- The row sent: `mp_product_row()` (base.php:772), remote code and id, title, tax-inclusive price in minor units, quantity, VAT rate. `mp_price_major()` (base.php:930) uses `BASE_CURRENCY_CODE`'s decimals: one currency a shop.
- Orders: `mp_import_order()` (`includes/api/outbound/orders.php:80`) takes a package (lines 52-79: ids, status, buyer, addresses, currency, lines in minor units) and writes `orders` with `type='marketplace'` and the package's `currency_code` (line 740).

## What did n11 and Trendyol each add?
- n11 (`connectors/n11.php`, 1,833 lines, table at line 48): categories, invoice link, collection request. Prices go in TL, or USD or EUR when the shop is in them (`mp_n11_currency()`, line 1696).
- Trendyol (`connectors/trendyol.php`, 4,494 lines, table at line 71): listing update, invoice link and mark, returns with approve and reject, questions and answers, deductions, the label trio, webhooks, buybox. It refuses a shop not in TRY for listings (lines 881-890) and for stock and price (lines 1470-1477).
- Merchant Center (`connectors/merchant_center.php:33`): credentials, stock and price, listing status; its account is a Google connection whose `connection_id` sits in the sealed JSON (line 49).
- Outside its file a provider is named in `includes/fn/connections_registry.php` (country 435-436, card 607-612), the search words at `includes/settings/registry.php:1681`, and the Win strategies, Trendyol only (`includes/fn/marketplace_pricing_settings.php:310`). No add-on can register a connector (`addon-hook-system-census-drivers.md`).
