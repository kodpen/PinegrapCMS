---
name: sales-channels-global
description: Plan (owner 2026-11-27, poll 246): products listed and orders taken from eBay, then Etsy, Amazon and TikTok Shop; the census is done, eBay's steps SC2 to SC7 need no table and no column
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): global sales channels. The list it belongs to and its rules: `plans/global-features.md`.
- What the connector base gives and what each marketplace asks: `plans/sales-channels-global-census.md` and its parts (SC1).

## What did the architect settle after the census? (2026-11-27; not the owner's words)
- The order is eBay, Etsy, Amazon, TikTok Shop, by what each costs to build and to get approved. Only eBay has steps; the next one's are written when SC7 has landed.
- Each site registers its own application at the marketplace, as every connected account does; one central application was put off by the owner (`workflow/decisions-made-panel-14.md`).
- Several countries of one marketplace are one account a country, the country in the account's sealed fields: no column. The category cache is keyed by the connector's own key, which carries the site.
- A price abroad is the base price converted when sent (`pg_currency_rate()`), until `plans/markets-price-lists.md` gives a price a country.
- No table and no column for eBay. What a later marketplace needs stored is asked of the owner then.

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- SC1 (S) The census: done.
- SC2 (M) eBay connected.
  - A provider of the connected accounts layer (`includes/connect/providers/ebay.php`): the authorization code flow with the application's RuName, sandbox and production apart, the three sell scopes.
  - The connector `connectors/ebay.php` with `test_credentials` and the account's fields: the eBay site, the inventory location and the three business policies, read from the account and chosen in selects. Named on Connections and in the settings search like the others.
  - The account deletion notice eBay asks before production keys: an address that answers the challenge and checks the signature. A notice is recorded and shown to the administrators with that buyer's orders; erasing stays the shop's, with the tool that exists.
- SC3 (M) Categories and pairing: the site's category tree and a category's required aspects into the cache under the connector's key; a product group paired with a category and its aspects on the screens the other marketplaces use.
- SC4 (M) Listing: inventory item, offer and publish for a product, an inventory item group for its variants, the "does not apply" text of the site for a missing identifier, the listing's state and its errors read back.
- SC5 (S) Stock and price sent in bulk through the queue, the price converted to the site's currency and rounded to its minor unit.
- SC6 (M) Orders read on the schedule (no notice subscription) into `mp_import_order()` in the order's own currency, and a shipment sent back with carrier and tracking number.
- SC7 (S) Close-out: `commerce/marketplace-ebay.md` and what the base gained; the census files of eBay deleted; the architect told, who writes Etsy's steps.

## What does every step keep?
- n11, Trendyol and Merchant Center work as they do; a base change is an ability a connector may leave out.
- Built from eBay's documentation with recorded answers as fixtures: no session has a seller account. The first try on eBay's sandbox is the owner's.
- Money in integer minor units; buyers' data only as far as the order needs.
