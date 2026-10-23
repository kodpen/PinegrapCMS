---
name: marketplace-listings
description: Rules for opening products on a marketplace (listings.php): product_group as article, category/attribute caching and unread attributes, SET order, Recycle Bin, stock tracking, n11 VAT.
---

## What is a listing "article"?
- A `product_group` that carries attributes. A group without attributes is a shelf in the store menu; offering it as a listing would make dozens of unrelated products the options of one listing. Filter: `product_groups_attributes_xref`.

## How are categories and attributes fetched?
- The category tree is cached PER PROVIDER. Attributes are fetched per category and on demand: fetching all of them is tens of thousands of calls against the per-minute limit.
- Attributes come from two sources: what the category wants from the product, answered once in the matching screen; and the variant attributes (Trendyol varianter and slicer, n11 isVariant), which take the product's own option first (`mp_listing_variant_value()`: the name, then the VALUE) and the article's value as fallback; two products with the same variant values refuse the article (`marketplace-variant-attributes.md`, PR #1239).

## What if the category's attributes cannot be read?
- The article is refused with the error (`mp_listing_definitions()`): shown at once by "List on the marketplace" and written on the article row by the job; the queue tries again when the connector said `retry` (timeout, 5xx, rate limit). It used to be read as an empty list, so the mandatory check passed and the article went out with no attributes, the variant one included (#916, PR #1238). Only the first listing of a category and a forced refresh are affected; the cache answers otherwise.

## What happens when the category changes?
- Old answers are deleted. MySQL evaluates `SET` left to right and a later assignment sees the NEW value of an earlier column, so `attributes_json` is assigned BEFORE `remote_category_id`.

## Which products are not listed?
- A product without stock tracking is not listed (a listing needs a quantity; a zero listing opens already sold out). For the same reason `quantity` is never sent in stock sends for a non-tracking product.

## Are products in the Recycle Bin listed?
- No. A binned product keeps its `products_groups_xref` row (so restore puts it back) but is switched off; sent, it opened a sold-out variant that stayed after the bin was emptied (#916, PR #1238). `mp_listing_articles()` (variant count), `mp_listing_items()` and `mp_listing_enqueue()` add `pg_catalog_bin_live_sql('products')`. A line queued before binning is not sent: `mp_sync_send_listings()` reads it with the bin filter in a `LEFT JOIN` (`mp_listing_split_live()`) and, before the lease, closes it `failed` with "{name} is in the Recycle Bin, so it was not listed." (deleted product: "The product no longer exists."). It shows under "What did not go through"; "Try again" after a restore lists it. The article row is untouched; an article whose lines are all binned is not assembled. It used to ride with the batch, get no SKU in the answer and close `done` with nothing listed (PR #PRNUM).

## What is a listed product's key?
- The seller's code (`products.name`) for n11. For a provider with `'key' => 'barcode'` (Trendyol) the mapped `remote_code`, else the product's first barcode; no barcode = article refused (`mp_listing_items()`, `trendyol-listings.md`). Items also carry `stock_code` (the name), `barcode`, `brand`, `taxable` and `price_major` = the checkout's tax-inclusive price (`marketplace-price.md`).

## Where does a refusal show?
- A non-retryable refusal from the connector's `create_listings` is written to the article row (`marketplace_category_map.last_error`) as well as to the queue rows; a retryable one only to the queue rows.

## How are VAT and images handled?
- VAT: n11 and Trendyol accept only 0/1/10/20; round to the nearest LOWER rate (rounding up would charge the buyer more).
- `products_images_xref` has NO `id` column: `ORDER BY id` is a fatal error.
