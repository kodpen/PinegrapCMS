---
name: merchant-center-push
description: Per-product push to Google Merchant Center rides the outbound marketplace layer (#4389): a managed provider, mapping rows from the feed, the queue, a supplemental data source; no table of its own
module: merchant_center
---

## Why the marketplace layer and not a table of its own? (#4389)
- Decided (architect, 2026-11-01): the outbound layer (`marketplace-outbound-layer.md`) already has what a push needs: accounts, one mapping row per product, a queue that carries instructions with a lease and backoff, refused rows written on the mapping, and `pg_marketplace_product_changed()` at every stock or price writer. A `merchant_product_sync` table would copy those (hash, job, retries) and still need the 5-minute runner; columns on `products` would widen the hot table against the layer's zero-columns rule. The Meta catalogue of the same plan takes the same path later.
- The credentials JSON of `marketplace_accounts` is opaque per provider, so `{connection_id, account, country}` pointing at the connected Google account (purpose `merchant_center`) is a credential like any other; `pg_connect_api()` renews the token.

## How is the account row made?
- `mp_providers()['merchant_center']`: no `fields`, `'managed' => true` (`marketplace-provider-fields.md`). The Product Feeds card writes the `marketplace_accounts` row when a Merchant Center account is chosen (`pg_merchant_state_save()`) and deletes it when the account is cleared; the Marketplaces screen never offers the provider under "add account", lists the account with its product matches, and links to the card instead of editing credentials.
- The supplemental API data source (linked to the feed's primary source: an insert into another primary source would take the product out of the file feed) is registered when the row is written, idempotent like the primary source.

## Which products are mapped, and how is a change sent?
- One `marketplace_product_map` row per product the feed offers: `remote_code` = the offerId `feed.php` writes (the product id), `remote_id` = Google's product name once read. Rows are added when the account is chosen and by the daily feed job with the feed's own product selection; a product that leaves the feed loses its row.
- `push_stock_price`: `productInputs.insert` into the supplemental source with price, sale price and availability, one call per product; a permanent 4xx comes back as `refused`, a 429 as `retry_after`. `listing_status` reads the product's status and issues into the mapping row.
- "Send to Merchant Center" on the product screen (#4388) makes no network call in the request: it enqueues with priority 1 and shows the last push and error from the mapping row; an on-demand status read may stay.
- Default: pushing is on once an account is chosen; the account's `push_stock` / `push_price` switches turn it off. A site with no account chosen sees no change.

Related: docs/memory/commerce/merchant-center.md, docs/memory/commerce/marketplace-outbound-layer.md
