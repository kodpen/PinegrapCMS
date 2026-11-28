---
name: etsy-listings
description: Etsy listings: one listing an article, draft, pictures uploaded as files, shared properties, inventory with the variants, active and read back; the listing id kept from the verdict; the fee notice
module: etsy
---

## Which calls list an article on Etsy?
- `mp_etsy_create_listings()` (`connectors/etsy.php`, entry `create_listings`), JSON bodies but the pictures: `GET /shops/{shop}` (currency), `POST /shops/{shop}/listings` (createDraftListing) or, for a listing an earlier send made, `GET /listings/{id}` then `PATCH /shops/{shop}/listings/{id}` without price and quantity.
- Then `POST …/images` a picture (multipart, `rank` from 1, `overwrite=true`); `PUT …/properties/{property}` for each property every product shares; `PUT /listings/{id}/inventory`; `PATCH …` with `state=active`.
- The answer to the activation is read back (`pg_etsy_listing_verdict()`); a listing Etsy keeps as draft or inactive is refused, "Etsy keeps the listing as …". Without a state in that answer, `GET /listings/{id}` is read.

## What is in the bodies? (pure, `includes/fn/etsy.php`)
- Draft: title (the article's; a single product's own title first), plain-text description (`pg_etsy_plain_text()`), price and quantity of the article, `who_made`, `when_made`, `taxonomy_id`, `shipping_profile_id`, `readiness_state_id`, `type=physical`.
- Variations (`pg_etsy_variations()`): a property whose values differ between the products is an option, at most two; the others are set on the listing. Products differing in nothing, in three properties or missing an option's value refuse the article.
- Inventory (`pg_etsy_inventory_body()`): a product a SKU with its option values (`value_ids` and `values`), one offering with price, quantity up to 999, `is_enabled` only with stock, `readiness_state_id`; price, quantity and SKU on every option property.
- Pictures (`pg_etsy_listing_images()`): the first product's in its order, then the others' new ones, at most 10, read from `data/files` (`pg_etsy_image_file_name()` takes only a plain file name under the site's address) and typed from their first bytes.

## What is refused before any call?
- The account's five choices not made; a product with no code or no picture; title empty or over 140 (never cut); variations as above; a picture that is not JPEG, PNG, GIF or WebP. After the shop read: no rate to the shop's currency, no price, nothing in stock.

## How is the price worked out?
- `pg_etsy_price()`: the price in minor units times the rate from the base currency to the shop's (`mp_etsy_rate()`), `round()` to the cent.

## How is the listing kept without a column?
- Etsy has no listing by SKU, so each verdict carries `content_id` = listing id. `mp_sync_apply_results()` writes a verdict's `content_id` on the mapping's `remote_id`, refused or not (a connector may leave it out). The next send reads it (`mp_etsy_known_listing()`) and updates that listing; one Etsy no longer has (404) gets a new draft.
- No answer, 429 or 5xx before a draft exists gives the article back to the queue; after it, every failure is the verdict with the listing id. Pictures above the ones sent stay on Etsy.
- `mp_listing_value()` now also gives a listed value's text as `value`; Trendyol and n11 ignore it.

## Where is the fee said?
- Connector entry `listing_fee` (`mp_etsy_listing_fee()`): Marketplace Products › Listings of an Etsy account shows it in an info alert until a mapping of the account has a listing id. USD 0.20 a listing, again at renewal or a sale of one of several, 6.5% of a sale.

## What is not verified?
- Built in ET3 of `plans/sales-channels-global.md` from Etsy's documentation; fixtures `tests/fixtures/etsy/listing-*.json`, `error-listing.json`; tests `tests/etsy_listings_test.php`. Not settled: JSON accepted on createDraftListing and updateListingProperty (documented as form fields), empty `value_ids` for a value of the shop's own, `readiness_state_id` on an offering. No `listing_status`: the id is known at once. The first try on a test shop is the owner's.
