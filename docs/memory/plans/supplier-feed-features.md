---
name: supplier-feed-features
description: Plan supplier-feed, features F1 to F14: the connection, field mapping, price rules, the hourly sync, closing and reopening, drafts, several suppliers, rates, cost link, run log, brakes, API
---

## How is a supplier connected?
- F1 A feed: name, address, format (XML, CSV, JSON; told from the first 8 KB when left on Automatic), optional user and password, key header or key in the address (encrypted), every 1, 3, 6, 12 or 24 hours, the feed's currency, whether its prices include VAT, an optional ERP supplier account. An http address needs "Allow an address without https" ticked (readable on the way) and takes only a key in the address, no user, password or key header (`allow_http`).
- F2 Field mapping: the first pull reads up to 200 records and proposes the fields from their names (Turkish and English names such as stok kodu, barkod, alış fiyatı, stok, marka, model kodu); the person confirms or changes each. The record path for XML and JSON is found the same way. Saved with the feed.
- F3 A test pull: what would be added, changed, closed and reopened, nothing written.

## How is the selling price worked out?
- F4 The rule: buy price (net of VAT) × (1 + margin %) + fixed amount, in the feed's currency turned into the home currency at today's rate; rules per category or brand, the most specific first, else the feed's own. Rounding: none, whole, ends in ,90 or ,99. A floor: never under buy price plus a minimum margin %.
- F5 A product can keep its own price: "Price from the feed" off on the product leaves price alone while stock still follows; the switch is kept on its `supplier_feed_items` row, no `products` column.

## What does a sync pull change?
- F6 Matched by the mapped code to `products.name`, else by barcode. Price through the product writer when it changes; stock through `api_inventory_apply()` set, only for a stock-tracked product.
- F7 A matched product missing from the pull or with stock 0 is closed in the same pull: stock 0 for a tracked product (page and back-in-stock notices stay), `enabled = 0` for an untracked one. Back in the feed with stock, the feed reopens only what it closed itself; a person's off stays off.
- F8 New records become drafts (`enabled = 0`) with their pictures, description, brand and category (a product group found by name, else the feed's mapped group); records sharing a model code become one variant set. A person publishes them.

## What about several suppliers?
- F9 One barcode in two feeds: the product follows the feed that has stock and the lowest buy price; price and stock both come from that feed; the choice is shown on the product.

## What follows a rate?
- F10 When Update Exchange Rates writes a rate, foreign-currency feeds' prices are worked out again from the stored buy prices, with no pull.

## How does it reach cost and planning?
- F11 Read only: no ERP table (`erp_product_costs` either) is written. Order profit: a line with no average cost takes the feed's buy price (net of VAT, home currency), kept as `item:<order_item_id>` with source `supplier` like an average line, never rewritten; such an order stays `estimate`. The stock plan's supplier: the feed's ERP account when no invoice names one.

## What is kept and told?
- F12 Each pull: one run row (times; records added, changed, closed, reopened, held; up to 500 failed lines with reason), 90 days kept; the screen lists runs, the product shows its feed and last seen time.
- F13 Brakes: an unreadable or empty feed, a missing mapped field, or more than half the matched products dropped in one pull: nothing written, the feed paused, an operations alert (`supplier_feed`). A buy price under half or over ten times its last value (one pure check) is held on its item row: the old price stays until Accept or Reject.

## Who can read it from outside?
- F14 `GET /supplier-feeds`, `GET /supplier-feeds/{id}/runs`, `POST /supplier-feeds/{id}/run` (scopes `products:read` and `products:write`), their types and MCP tools by the route rule. The address, user and key are never returned.
