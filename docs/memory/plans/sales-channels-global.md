---
name: sales-channels-global
description: Plan (owner 2026-11-27, poll 246): products listed and orders taken from eBay (built), then Etsy, Amazon and TikTok Shop; Etsy's steps ET1 to ET6 need no table and no column
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): global sales channels. The list it belongs to and its rules: `plans/global-features.md`.
- What the connector base gives and what each marketplace asks: `plans/sales-channels-global-census.md` and its parts.

## What did the architect settle? (2026-11-27; not the owner's words)
- The order is eBay, Etsy, Amazon, TikTok Shop, by what each costs to build and to get approved. eBay is built: `commerce/marketplace-ebay.md` (SC2 to SC7). The next one's steps are written when ET6 has landed.
- Each site registers its own application at the marketplace, as every connected account does (`workflow/decisions-made-panel-14.md`).
- A price abroad is the base price converted when sent, as eBay's (`commerce/ebay-stock-price.md`).
- Etsy: one account a shop, its currency the shop's. No table, no column, no remote picture id kept: pictures are uploaded when a listing is made and again only when the user sends them again.
- Etsy gives a key made in Turkey no addresses and no shipment call (`sales-channels-global-census-etsy.md`). Such a shop gets listings, stock, price and its orders without an address; the account's screen says so. Nothing is built apart for it.

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- ET1 (M) Etsy connected.
  - Provider `includes/connect/providers/etsy.php`: the code flow with PKCE, keystring and shared secret, `x-api-key` on every call, the scopes the steps below need.
  - Connector `connectors/etsy.php` with `test_credentials`: the shop read from the sign-in; shipping profile, readiness state, who made it and when in remote selects. Named on Connections and in the settings search.
  - Its setup lines say what Etsy lets a shop sell, the listing fee, the key's tiers and that tests run on a test shop.
- ET2 (S) Taxonomy and pairing: the seller taxonomy and a node's properties into the cache under the provider's key; a product group paired on the screens the other marketplaces use.
- ET3 (M) Listing: a draft, its pictures uploaded as files in the product's order, the inventory with the variants, then active. The fee is said before the first publish; the listing's state and errors are read back.
- ET4 (S) Stock and price through the queue: one listing a call with its whole products array, the price converted to the shop's currency, paced by the limits the answers carry.
- ET5 (M) Orders and shipment: paid receipts changed since the last read into `mp_import_order()` in the receipt's currency; a parcel sent back with tracking code and carrier. A receipt without an address is imported so, marked "Etsy gives this application no address", and its shipment is reported on Etsy. If the base refuses an order without an address, the step asks the architect.
- ET6 (S) Close-out: `commerce/marketplace-etsy.md` and what the base gained; Etsy's census file deleted; the architect told, who writes Amazon's steps.

## What does every step keep?
- n11, Trendyol, eBay and Merchant Center work as they do; a base change is an ability a connector may leave out.
- Built from Etsy's documentation with recorded answers as fixtures: no session has a shop. Etsy has no sandbox; the first try on a test shop is the owner's.
- Orders only by the scheduled pull, no notice subscribed. Money in integer minor units; buyers' data only as far as the order needs.
- Screens in the look; an old screen a step touches is brought over (`workflow/decisions-made-product-118.md`).
