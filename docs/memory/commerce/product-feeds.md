---
name: product-feeds
description: Product feed builder (includes/fn/product_feeds.php, PR #561): which products go into the OpenAI JSONL/CSV and Google TSV feeds, field rules, skip reasons, variant families, writers
---

## What exists?
- `includes/fn/product_feeds.php` builds feeds in `openai_jsonl`, `openai_csv` or `google_tsv`: `pg_product_feed_render()` from items in memory, the daily build streamed (docs/memory/commerce/product-feed-streaming.md). Setup, daily build and feed.php: docs/memory/commerce/product-feed-serving.md. The catalog page's `?rss=true` feed is untouched.
- Pure parts tested in `tests/product_feeds_test.php`; the reader was checked on a sandbox install (Turkish template).

## Which products go in?
- Enabled products in a group the shop's catalogs show: `pg_product_feed_catalog_roots()` takes the group of each legacy catalog page and each Catalog widget (0 = the top-level group) whose page is in a public folder, and `pg_product_feed_group_index()` walks enabled groups under them. Templates keep dues, exam, event and membership groups beside the shop under the top group; without this scope they would be listed.
- Left out, with a reason count in `skipped`: `no_address` (no address_name), `donation`, `recurring`, `no_price` (price ≤ 0), `not_in_catalog`, `excluded_group` (any of its groups under `excluded_group_ids`).
- Whole-feed errors: `ecommerce_off`, `no_catalog`, `no_detail_page`, `detail_page_not_public`, `no_seller_name` (OpenAI formats), `unknown_format`.

## How is a product described?
- URL: site base + `pg_sw_catalog_detail_page_name(0)` + `/` + address_name, as the cart and express order link products. Images: `image_name` then `products_images_xref` by file name (as the product page), absolute, max 1 + 10.
- Title: `_pg_product_rss_title()` (same as RSS/JSON-LD), 150 chars. Description: plain text of `full_description`, else meta, else short description, else the title; 5000 chars. Text fields are entity-decoded, not tag-stripped ("<10 cm" stays).
- Price: `products.price` as "1499.00 TRY" built from integers; `sale_price` only when the offer price for everybody (`get_discounted_product_prices(false)`, below) is above 0 and below the price.
- GTIN only with a valid GS1 check digit (spaces/dashes dropped). Brand: product brand, else `default_brand` option.
- Variant family: the product's first select-type group gives `group_id` "G<id>" (never equals an item id) and the attribute choices (label without a trailing colon, "nothing selected" options skipped).

## Whose offer prices are published?
- A guest's, whoever builds the feed (panel "Save and build now", `product_feed_job.php` from a manager's browser, the daily job): `get_discounted_product_prices(false)` switches `pg_offer_guest_view()` on while it runs, so the offer checks read no account, no offer code and an empty cart. Coded and key-code offers, cart conditions and rules stay out; `new customer` in its `no_orders` mode is met. The RSS feeds use the same call.
- Before #747 the run read the requester's session: a manager who had opened a product with `?o=CODE` published the coded price as `sale_price` until the next daily build.

## What differs per format?
- OpenAI: needs title, brand, a JPEG/PNG image, price; `is_eligible_search` true and `is_eligible_checkout` always false until the storefront API exists; seller URL/privacy/terms only when given.
- Google: brand optional; WebP/GIF images allowed; back order is `in_stock` (as in RSS; Google's backorder needs a date); `identifier_exists` "no" without GTIN and MPN; `product_type` = category trail.

## How are files written?
- JSONL: one object per line, unescaped Unicode and slashes, invalid UTF-8 substituted. CSV by hand (RFC 4180 quoting, no fputcsv escape rules); TSV with tabs and line breaks turned into spaces. Lists comma-joined with `%2C` inside URLs; `variant_dict` cast to an object so numeric labels stay an object. Only filled columns are written; an empty feed still has the required header.
