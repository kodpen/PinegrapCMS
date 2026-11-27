---
name: marketplace-ebay
description: eBay marketplace (Connections › eBay, Marketplace Products): its lasting rules, what the connector base gained for the next marketplace, which ebay-*.md holds what
module: ebay
---

## What does eBay do here?
- A site connects its own eBay application on Connections › eBay (or eBay (sandbox)), adds one marketplace account a site, chooses location and three business policies, pairs product groups with eBay categories, lists articles, sends stock and price through the queue, reads orders on the schedule and reports parcels back. Built in steps SC2 to SC6 (PR #12163 the last).
- No table and no column was added for eBay; everything sits in the account's sealed credentials, the connected accounts layer and the existing marketplace tables.

## Which rules bind it?
- Architect (2026-11-27): each site registers its own eBay application, as every connected account does; one central application was put off by the owner (`workflow/decisions-made-panel-14.md`).
- Architect (2026-11-27): one marketplace account a country (eBay site), the site in the sealed credentials; one sign-in serves every site's account of an environment.
- Architect (2026-11-27): a price abroad is the base price converted when sent (`pg_currency_rate()` of today, rounded to the site's cent), until `plans/markets-price-lists.md` gives a price a country.
- Orders only by the scheduled pull: no eBay order notice is subscribed.
- n11, Trendyol and Merchant Center work as before: every base change below is an ability a connector may leave out.

## What did the connector base gain?
- `category_key($account)`: the category cache is keyed by `mp_category_key()`, the connector's key or the provider (eBay: `ebay:EBAY_DE`), so a marketplace with a tree a country needs no column (`ebay-categories.md`).
- Remote selects: a field `'type' => 'select', 'remote' => true` gets its options from `test_credentials()`'s `data['choices']` (`ebay-connection.md`).
- Connect providers: `'redirect_option' => 'runame'` sends a saved value as `redirect_uri`; `copy_values` shows values with Copy buttons in the app form (`ebay-connection.md`).
- `create_listings` may answer verdicts at once (`data['results']`, `task_id` ''): the lines get a local task id `now:<16 hex>` (`ebay-listings.md`). `mp_listing_items()` fills `barcode` for every provider.
- An attribute with values and `custom_allowed` is a text box with suggestions on the attribute dialog, for every marketplace (`ebay-categories.md`).
- A provider row may say `ships_itself` and `open_statuses` (`mp_ship_open_statuses()`); `ship_package` gets `$package['parcel']` with the carrier key and name and may answer `data['remote_status']` (`ebay-orders.md`, `marketplace-outbound-layer.md`).
- `mp_order_email_is_personal()` lists relay domains (`members.ebay.com`) that match no customer.

## Which file holds what?
- Sign-in, account fields, remote selects, the connector test: `ebay-connection.md`.
- The account deletion address eBay asks before production keys: `ebay-account-deletion.md`.
- Categories and item specifics: `ebay-categories.md`. Listing, variants, "Does not apply": `ebay-listings.md`.
- Stock and price: `ebay-stock-price.md`. Orders and shipment: `ebay-orders.md`.

## What is not verified?
- Nothing was tried against eBay: no session has keys or a seller account; fixtures under `tests/fixtures/ebay/` follow eBay's documentation. The first sandbox try is the owner's.
- Left open by eBay's pages: whether `bulkUpdatePriceQuantity` takes 25 SKUs a call (the guide) or one SKU's offers (the reference); whether a carrier name off eBay's list is taken; VAT and the EU's product safety fields are not sent.
