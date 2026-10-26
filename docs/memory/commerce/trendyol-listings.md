---
name: trendyol-listings
description: Trendyol V2 listing create (connectors/trendyol.php): barcode as the key, brand id by name at send time, what is refused before the call, batch result per barcode, what SUCCESS means.
---

## Which calls open a listing?
- `POST /integration/product/sellers/{sellerId}/v2/products`, body `{"items":[...]}` (max 1000), answers `batchRequestId`. Then `GET .../sellers/{sellerId}/products/batch-requests/{id}` (no V2 segment; kept 4 hours). Docs read 2026-10-20 (create page modified 2026-09-18).
- Required per item: `barcode` (≤40, letters incl. Turkish, digits, `.` `-` `_`), `title` (≤100), `productMainId` (≤40, groups variants), `brandId`, `categoryId` (leaf), `quantity`, `description` (HTML ≤30000), `listPrice` ≥ `salePrice`, `vatRate` (0/1/10/20), `images` (https, max 8), `attributes` (`attributeValueId` or `customAttributeValue`). No `currencyType` and no `cargoCompanyId` in V2; `cargoProviders`, addresses and `deliveryOption.deliveryDuration` are optional.

## What does the connector send?
- `barcode` = item `code`; `stockCode` = product name (≤100); `productMainId` = product group name; both prices = the checkout's tax-inclusive price (`mp_product_price()`, `marketplace-price.md`); VAT rounded down to an allowed rate, 0 for an untaxed product; `deliveryOption.deliveryDuration` = account "Days to prepare". Cargo, addresses and dimensional weight are left to the seller's Trendyol defaults.

## Why is the barcode the key?
- Trendyol names every batch verdict, stock line and order line by barcode. `mp_providers()['trendyol']['key'] = 'barcode'` makes `mp_listing_items()` use the product's mapped `remote_code` or else its first (oldest) `product_barcodes` row as `code`, so the mapping row and `mp_sync_apply_results()` match on the barcode. A product without one is refused; no barcode is invented. The match screen's blank code means the barcode too.

## How is the brand found?
- `products.brand` (text). At send time, in the job, `GET /integration/product/brands/by-name?name=` once per distinct name (bare list, case-sensitive): exact name wins, else a single case-only match, else the article is refused. Brands are never created on Trendyol from here.

## What is refused before any call?
- Shop currency not TRY, no brand, unknown brand, barcode against their rules, model code empty or >40, stock code >100, no title, no/too long description (never cut: broken HTML), no price, a picture not on https (dev with `http` scheme fails here), no picture. The connector's refusal (retry false) is also written to the article row (`marketplace_category_map.last_error`) by `mp_sync_send_listings()`.

## How is the batch read?
- Finished when `status` is `COMPLETED`; a batch without `status` (stock/price) when every line has one. Per line: barcode from `requestItem.barcode` or `requestItem.product.barcode`, ok only for `SUCCESS`, reason from `failureReasons` (strings, or objects read by message/reason/description/key).
- SUCCESS means "sent to Trendyol's approval", not on sale. Approval and rejection are read afterwards onto the mapping row (`trendyol-listing-status.md`).

## How is an approved listing changed?
- By content id, title/description/pictures only: `trendyol-listing-update.md`.

Related: docs/memory/commerce/trendyol-api-facts.md
