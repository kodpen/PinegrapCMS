---
name: marketplace-listings
description: Rules for opening products on a marketplace (listings.php): product_group as article, category/attribute caching, SET order, stock tracking, n11 VAT.
---

## What is a listing "article"?
- A `product_group` that carries attributes. A group without attributes is a shelf in the store menu; offering it as a listing would make dozens of unrelated products the options of one listing. Filter: `product_groups_attributes_xref`.

## How are categories and attributes fetched?
- The category tree is cached PER PROVIDER. Attributes are fetched per category and on demand: fetching all of them is tens of thousands of calls against the per-minute limit.
- Attributes come from two sources: what the category wants from the product, answered once in the matching screen; and the variant-distinguishing attribute, taken from the product's own option (`mp_listing_variant_value()` looks at the name first, then the VALUE).

## What happens when the category changes?
- Old answers are deleted. MySQL evaluates `SET` left to right and a later assignment sees the NEW value of an earlier column, so `attributes_json` is assigned BEFORE `remote_category_id`.

## Which products are not listed?
- A product without stock tracking is not listed (a listing needs a quantity; a zero listing opens already sold out). For the same reason `quantity` is never sent in stock sends for a non-tracking product.

## What is a listed product's key?
- The seller's code (`products.name`) for n11. For a provider with `'key' => 'barcode'` (Trendyol) the mapped `remote_code`, else the product's first barcode; no barcode = article refused (`mp_listing_items()`, `trendyol-listings.md`). Items also carry `stock_code` (the name), `barcode`, `brand`, `taxable` and `price_major` = the checkout's tax-inclusive price (`marketplace-price.md`).

## Where does a refusal show?
- A non-retryable refusal from the connector's `create_listings` is written to the article row (`marketplace_category_map.last_error`) as well as to the queue rows; a retryable one only to the queue rows.

## VAT and images
- VAT: n11 and Trendyol accept only 0/1/10/20; round to the nearest LOWER rate (rounding up would charge the buyer more).
- `products_images_xref` has NO `id` column: `ORDER BY id` is a fatal error.
