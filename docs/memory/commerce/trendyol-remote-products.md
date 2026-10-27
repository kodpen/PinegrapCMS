---
name: trendyol-remote-products
description: Trendyol catalogue read for bulk matching (mp_trendyol_remote_products()): approved products call, one row per variant keyed by barcode, fields, last page, unverified paging cap
---

## Which call reads the seller's catalogue?
- `GET /integration/product/sellers/{sellerId}/products/approved?page=N&size=100`, pages from 0, read whole (8 MB). Answer, shaped after the docs and the listing-status fixture: `{totalElements, totalPages, page, size, nextPageToken, content[{contentId, productMainId, title, images[{url}], variants[{barcode, stockCode, onSale, locked, archived, blacklisted, stock{quantity}, price{salePrice, listPrice}, images?}]}]}`.
- Only approved products: one awaiting approval or rejected cannot take stock yet, and the listing status read follows those.
- Driven by `mp_remote_products_read()` like n11 (`marketplace-bulk-matching.md`); the screen needed no change.

## What row does a variant give?
- One row per variant, since a variant is what Trendyol sells, stocks and prices. A variant without a barcode is left out.
- `remote_code` and `barcode` = the barcode: every stock and price push names it, so a match is written with it (n11 uses the stock code).
- `remote_id` = `contentId`, the value the listing status read writes on a mapping row; `group_code` = `productMainId`, shared by a content's variants; `title` = the content's title.
- `image_url`: the variant's first https picture, else the content's; http addresses are dropped (the panel is https).
- `price` = `price.salePrice` (or a flat `salePrice`) through `mp_trendyol_minor()` (`round()`); `quantity` = `stock.quantity` (or flat `quantity`).
- `status`: the true flags among `onSale`, `locked`, `archived`, `blacklisted`, joined with `/`.

## When is it the last page?
- An empty page, or `page + 1 >= totalPages`. Trendyol has no `last` flag here.

## What is unverified?
- No live call (`tests/marketplace_trendyol_remote_products_test.php` uses a fake transport). Whether the service caps `page x size` (orders have a 10,000-row window) and wants `nextPageToken` past it: paging is by number only, so a catalogue past such a cap would stop with an error or an empty page.

Related: docs/memory/commerce/marketplace-bulk-matching.md, docs/memory/commerce/trendyol-api-facts.md, docs/memory/commerce/trendyol-listing-status.md
