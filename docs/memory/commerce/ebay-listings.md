---
name: ebay-listings
description: eBay listings: inventory item, variant group, offer and publish, price in the site currency, the "does not apply" identifier, verdicts at once, listing id read back
module: ebay
---

## Which calls list an article on eBay?
- `mp_ebay_create_listings()` (`connectors/ebay.php`, entry `create_listings`), Inventory API with the seller's token and `Content-Language` = the site's locale: `PUT inventory_item/{sku}` for each product; for two or more products `PUT inventory_item_group/{key}`; each offer found by `GET offer?sku=&marketplace_id=` and updated (`PUT offer/{id}`) or created (`POST offer`); then `POST offer/{id}/publish`, or for a group `POST offer/publish_by_inventory_item_group` (all variants or none).
- SKU = item `code` (the product name). Group key = product group name when `[A-Za-z0-9._-]{1,50}`, else `pg-` + 24 hex of its md5 (`pg_ebay_group_key()`).

## What is in the bodies? (pure, `includes/fn/ebay.php`)
- Item: condition `NEW`, quantity, title, plain-text description cut at a word to 4,000, aspects by name (`pg_ebay_aspects()`: value id is the text), up to 24 pictures, identifier. Group: shared aspects, `variesBy.specifications` = aspects whose values differ; no differing aspect refuses the article. Title is the group's.
- Offer: `FIXED_PRICE`, the category, full HTML as `listingDescription`, the account's location and three policies, price in the site's currency.

## What is the "does not apply" identifier?
- `pg_ebay_identifier()`: 12 digits UPC; 8, 13, 14 digits EAN; else the site's text (`pg_ebay_does_not_apply()`: "Does not apply", DE/AT/CH "Nicht zutreffend", FR "Non applicable", IT "Non applicabile", ES "No aplicable", NL/BE "Niet van toepassing", PL "Nie dotyczy") under UPC on EBAY_US/EBAY_CA, EAN elsewhere. The barcode is the product's first (`mp_listing_items()` now fills `barcode` for every provider, refusing none for it outside barcode-keyed ones).

## How is the price worked out?
- `pg_ebay_offer_price($price_major, $rate)`: price in hundredths, times the rate from the shop's currency (`mp_ebay_rate()`, `pg_currency_rate()` of today, 1 for the same currency), `round()` to the cent. No rate refuses the article naming both currencies. SC5's stock and price send reuses it.

## What is refused before any call?
- No location or policy chosen; no rate; SKU empty or over 50; title empty or over 80 (never cut); a picture not on https; no price. Written on the article row by `mp_sync_send_listings()`.

## How are verdicts read back?
- eBay answers at once, so the connector answers `data['results']` (SKU => ok, error) with `task_id` ''. `mp_sync_send_listings()` then gives the lines a local task id `now:<16 hex>` and calls `mp_sync_apply_results()` after writing the mappings: a refused SKU's mapping goes to `error` with eBay's messages (up to three, `pg_ebay_error_messages()`).
- No answer, 429 or 5xx on any call gives the whole article back to the queue (every call puts or replaces). In a group, one refused variant leaves the others "Not published".

## How is the listing's state read?
- `mp_ebay_listing_status()` (entry `listing_status`): one `GET offer?sku=` a SKU. A `PUBLISHED` offer with a listing id is `approved`, the listing id written as `remote_id`; listing `ENDED` noted as archived; anything else, or 404, `unknown` (eBay reviews nothing). The shop view then shows "Awaiting approval" for a row not yet read.

## What is not verified?
- Fixtures from eBay's documentation (`tests/fixtures/ebay/offers*.json`, `offer-*.json`, `publish.json`, `error-*.json`); tests `tests/ebay_listings_test.php`. Not sent: VAT fields, EU product safety fields, brand as a separate field (an aspect). The site texts other than the English one were not checked against eBay's page; TW and HK take the English one. The first try on the sandbox is the owner's.
