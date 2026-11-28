---
name: marketplace-etsy
description: Etsy marketplace (Connections › Etsy, Marketplace Products): its lasting rules, what the connector base gained for the next marketplace, which etsy-*.md holds what, what registering asks
module: etsy
---

## What does Etsy do here?
- A site connects its own Etsy app on Connections › Etsy, adds one marketplace account a shop, chooses shop, shipping profile, processing profile, who made and when made, pairs product groups with Etsy's seller taxonomy, lists articles, sends stock and price through the queue, reads paid receipts on the schedule and reports parcels back. Built in steps ET1 to ET5 (PR #12271 the last).
- No table and no column was added; everything sits in the account's sealed credentials, the connected accounts layer and the existing marketplace tables.

## Which rules bind it?
- Architect (2026-11-27): each site registers its own Etsy app, as every connected account does (`workflow/decisions-made-panel-14.md`).
- Architect (2026-11-27): one account a shop, in the shop's currency; a price is the base price converted when sent, as eBay's. No remote picture id is kept: pictures are uploaded when a listing is made and again only when it is sent again.
- Architect (2026-11-27): an app made in Turkey gets no buyer addresses and no shipment call (Etsy's 3 June 2024 release notes); such a shop gets listings, stock, price and its orders without an address, the account's help says so, nothing is built apart for it (`etsy-orders.md`).
- Orders only by the scheduled pull, no Etsy webhook subscribed. n11, Trendyol, eBay and Merchant Center work as before.

## What did the connector base gain?
- Connect providers: `'client_auth' => 'none'` sends no secret on the token request (PKCE apps); `'app_headers' => '<function>'` adds headers to token requests through `pg_connect_app_headers()` (`etsy-connection.md`).
- A `create_listings` verdict may carry `content_id`: `mp_sync_apply_results()` writes it on the mapping's `remote_id`, refused or not, so a marketplace with no listing by SKU keeps its listing id without a column (`etsy-listings.md`).
- `mp_listing_value()` also gives a listed value's text as `value`.
- Connector entry `listing_fee`: Marketplace Products › Listings shows its text in an info alert until a mapping of the account has a listing id.
- An imported package may carry `notice`: `mp_import_claimed()` keeps it in the map row's `last_error`, the Imported orders card shows it, and `mp_sync_ship_pending()` passes the package by (`etsy-orders.md`).
- A category tree one for all shops needs no `category_key`: the cache stays under the provider's key (`etsy-categories.md`).

## Which file holds what?
- Sign-in, account fields, remote selects, the connector test: `etsy-connection.md`.
- Taxonomy, properties, pairing: `etsy-categories.md`. Listing, pictures, variations, the fee: `etsy-listings.md`.
- Stock and price, pacing by limit headers: `etsy-stock-price.md`. Orders and shipment: `etsy-orders.md`.

## What does registering ask of the owner?
- Tiers: a seller's app for its own shop; personal, up to 5 shops; commercial, reviewed by Etsy by hand. No API fee is named. The app shows Etsy's trademark notice; the shared secret is never published.
- Etsy allows handmade, vintage and craft supplies; mass-produced goods bought to resell are not allowed. Etsy takes USD 0.20 a listing and 6.5% a sale.
- No sandbox: the first try is on a test shop, and it is the owner's.

## What is not verified?
- Nothing was tried against Etsy: no session has a shop; fixtures under `tests/fixtures/etsy/` follow Etsy's documentation and OpenAPI file. Each etsy-*.md lists its open points.
- Not read (403 or not opened): Etsy's API terms (`www.etsy.com/legal/api`), the API testing policy, the trademark policy, `www.etsy.com/developers`.
