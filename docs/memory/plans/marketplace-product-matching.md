---
name: marketplace-product-matching
description: Plan (approved 2026-10-26): match a shop's products to its marketplace catalogue in bulk, with pictures, on a new Marketplace products screen; step 1 done (#1880), step 2 next
---

## Why?
- A live n11 store (2026-10-26) could only be matched one product at a time on the account screen: a text code box and a product picker without pictures. The shop's codes are not the codes it uses on n11, so "leave the code blank" did not help either. The owner asked for bulk matching and a new interface built around pictures.

## What is decided?
- Owner (2026-10-26): the marketplace's catalogue is kept in a new table `marketplace_remote_products` (one row per remote product and account), not in a file.
- Owner (2026-10-26): a separate full-width screen, `marketplace_products.php?account=N`, reached from the account screen; the account screen keeps settings, orders, returns and questions.
- Suggestions come from the barcode, the code (whole or contained) and the title (weighted words, numbers count more), never a guess without a reason shown. Matches are one to one, as `marketplace_product_map` requires.
- Nothing is matched without a click: one row's tick, a picked product, or "accept the sure ones". Matching sends nothing, as the old map form did.
- A connector reads its catalogue a page at a time (`remote_products`); the screen reads pages in a loop with a progress bar, so a 10,000-product store needs no long request.

## What remains?
1. ~~Remote catalogue, suggestions and the review screen for n11.~~ Done #1880 (`commerce/marketplace-bulk-matching.md`).
2. Products workspace on the same screen: every shop product with its picture and its state on the account (matched code, last sent, error, listing approval), filters, bulk match by own code, unmatch, send now; the account screen's "Matched products" card becomes a summary with a link.
3. Listings on the same screen: article cards with pictures, category search by path, one category for several articles, required attributes first and optional ones folded; the old listing card goes.
4. Trendyol `remote_products` (its product filter service, barcode as the key), so Trendyol accounts get the same suggestions.

## Open questions?
- Whether n11 products sent from the n11 warehouse (`sender=N11`) should be read too; step 1 reads the default (the seller's own).
- Owner: whether `marketplace_product_map.remote_code` (2026.4.4, `utf8mb4_unicode_ci`) becomes `utf8mb4_bin` in a schema step. Step 1 compares it byte for byte in its own queries; the order import's lookup by stock code (`orders.php`) still treats "ABC-1" and "abc-1" as one code.

## Where to start?
- Step 2, after `docs/memory/commerce/README.md`, `marketplace-bulk-matching.md` and `marketplace-outbound-layer.md`. One step per PR, in order.
