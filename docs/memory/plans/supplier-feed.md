---
name: supplier-feed
description: Plan (owner 2026-11-20, poll 168): the store pulls its suppliers' XML, CSV or JSON lists and keeps price and stock in step hourly; price rules, drafts, several suppliers, cost link; SF1 to SF7
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 168, message 10315): "Evet, plana girsin": the connection with field mapping, price rules, hourly price and stock sync with products closed and reopened, new products as drafts, several suppliers on one barcode, the buy price as cost, a run log, a stop on a broken feed.

## What is there today?
- Feeds go out only (`includes/fn/product_feeds.php`, `commerce/product-feeds.md`).
- One-off imports (`platform/import-framework.md`): readers and writers, `imports` / `import_items`, the job tick, nothing overwritten (`platform/import-decisions.md`); `includes/import/write_product.php`, `pg_import_media_fetch()` for pictures, XMLReader in the WordPress reader.
- Outbound reads: `api_http_request()` (`includes/api/outbound/http.php`): address check, pinned address, no redirects, a body streamed to a file.
- Stock: `api_inventory_apply()` (atomic, logged, `inventory.changed`, marketplace hook). Prices through the API's product writer, recorded in `product_price_history`.
- Cost: `erp_product_costs` from purchase invoices only (`erp/erp-stock-moves-and-cost.md`), read by order profit (`erp/erp-order-profit-record.md`); the stock plan's supplier is the latest purchase invoice's account (`erp/erp-stock-plan-forecast.md`).
- Rates: `currencies.exchange_rate`, the Update Exchange Rates job. No product is tied to a supplier.

## What binds it?
- A recurring sync, not an import: it writes price and stock on purpose, so it keeps its own tables and leaves `imports` and its "nothing overwritten" rule alone.
- Every read through `api_http_request()`; a feed's password or key in one encrypted blob (`core/security-helpers-and-secret-storage.md`), never shown back.
- Stock only through `api_inventory_apply()`, price only through the product writer, so marketplaces, webhooks, price history and the audit trail see each change.
- Works with Accounting off; ERP tables are only read, never written.
- A product a person turned off is never turned on by a feed.
- Large feeds: streamed to `data/temp/`, read row by row with a resumable cursor in job ticks, one lock per feed.

## What does the architect decide?
- Schema (the owner's, through the architect): `supplier_feeds`, `supplier_feed_items` (feed, supplier key, product, barcode, buy price in minor units, currency, stock, seen, closed by the feed), `supplier_feed_runs`. Or another shape.
- Cost: the buy price as a third cost source that order profit reads only where `erp_product_costs` has none, never written into it? And the stock plan's supplier from the feed's linked ERP account when no invoice names one?
- Closing a dropped product: stock 0 for a tracked one (its page and back-in-stock notices stay), `enabled = 0` for an untracked one?
- Plain http feeds (common, often with the key in the address): allowed per feed with a warning, or https only?
- A single item's buy price falling under half its last value: held for a person's yes, not written?
- The screen: Products › Supplier feeds, with the product screens' right?

## What is built?
- Features: `supplier-feed-features.md` (F1 to F14). Steps: `supplier-feed-steps.md`. Claim as `[plan:supplier-feed] <step> — <topic>`.

## What is left out?
- Sending orders to the supplier, purchase invoices made from the feed, the store's own outbound feed for resellers, FTP or SFTP sources: each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the screen is seen on dev after SF2.
