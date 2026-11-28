---
name: sales-channels-global
description: Plan (owner 2026-11-27, poll 246): products listed and orders taken from eBay and Etsy (built), then Amazon and TikTok Shop; Amazon's steps AZ1 to AZ8 need no table and no column
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): global sales channels. The list it belongs to and its rules: `plans/global-features.md`.
- What the connector base gives and what each marketplace asks: `plans/sales-channels-global-census.md` and its parts.

## What did the architect settle? (not the owner's words)
- The order is eBay, Etsy, Amazon, TikTok Shop. Built: `commerce/marketplace-ebay.md`, `commerce/marketplace-etsy.md`. TikTok Shop's steps are written when AZ8 has landed.
- Each site registers its own application (`workflow/decisions-made-panel-14.md`); a price abroad is the base price converted when sent (`commerce/ebay-stock-price.md`).
- Amazon's settlements of 2026-11-28 (private application, an account a country, no FBA orders, the keep limit as a Privacy centre rule): `plans/sales-channels-global-amazon.md`.

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- AZ1 (M) Amazon connected.
  - Provider `includes/connect/providers/amazon.php`: client id and secret, the refresh token the seller copies from Seller Central; the connect layer gains a connection made from a pasted refresh token, an ability a provider may leave out.
  - Connector `connectors/amazon.php` with `test_credentials`: the seller id, the marketplace as a remote select of those the seller takes part in, the region's endpoint from it. Named on Connections and in the settings search.
  - Its setup lines: registering as a private developer, the roles to ask for, the secret's 180-day rotation, Amazon's data rules.
- AZ2 (S) Product types and pairing: types searched by name and a type's required attributes into the cache under the account's `category_key`; a product group paired on the screens the other marketplaces use.
- AZ3 (M) An offer on a catalogue product: the article's barcode matched in Amazon's catalogue, the offer validated in preview and sent; an article without a barcode or a match is refused with its reason; state and issues read back.
- AZ4 (M) A product the catalogue lacks: sent with the type's required attributes of the shapes the pairing screen holds and its pictures as the site's URLs; a required attribute of another shape is named in the listing's error.
- AZ5 (S) Stock and price through the queue: one article a call, the quantity the seller ships and the price in the marketplace's currency, paced by the limits the answers carry.
- AZ6 (M) Orders and shipment: seller-shipped orders changed since the last read into `mp_import_order()` in the order's currency, at the Orders API's slow pace; a parcel reported with carrier and tracking number. Without an address: imported with the `notice`.
- AZ7 (S) The Privacy centre's rule "Amazon orders": buyer data 30 days after delivery, through the erasure's own order entries, off by default; the account's help names it.
- AZ8 (S) Close-out: `commerce/marketplace-amazon.md` and what the base gained; Amazon's census file and `sales-channels-global-amazon.md` deleted; the architect told, who writes TikTok Shop's steps.

## What does every step keep?
- n11, Trendyol, eBay, Etsy and Merchant Center work as they do; a base change is an ability a connector may leave out.
- Built from Amazon's documentation (`sales-channels-global-census-amazon.md` names the pages; a step reads its own again) with recorded answers as fixtures: no session has a seller account and the sandbox gives fixed answers only. The first try on a real account is the owner's.
- Orders only by the scheduled pull, no notice subscribed. Money in integer minor units; buyers' data only as far as the order needs.
- Screens in the look; an old screen a step touches is brought over (`workflow/decisions-made-product-118.md`).
