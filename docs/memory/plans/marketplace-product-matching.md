---
name: marketplace-product-matching
description: Plan (approved 2026-10-26): bulk matching of a shop's products to its marketplace catalogue, with pictures, on the Marketplace products screen; steps 1-5 done; only the n11 warehouse question is open
---

## Why?
- A live n11 store (2026-10-26) could only be matched one product at a time on the account screen: a text code box and a product picker without pictures. The shop's codes are not the codes it uses on n11, so "leave the code blank" did not help either. The owner asked for bulk matching and a new interface built around pictures.

## What is decided?
- Owner (2026-10-26): the marketplace's catalogue is kept in a new table `marketplace_remote_products` (one row per remote product and account), not in a file.
- Owner (2026-10-26): a separate full-width screen, `marketplace_products.php?account=N`, reached from the account screen; the account screen keeps settings, orders, returns and questions.
- Owner (2026-10-26): `marketplace_product_map.remote_code` becomes `utf8mb4_bin`, as `marketplace_remote_products.remote_code` is; the order import keeps the case-insensitive match as its fallback (`workflow/decisions-made-data.md`).
- Suggestions come from the barcode, the code (whole or contained) and the title (weighted words, numbers count more), never a guess without a reason shown. Matches are one to one, as `marketplace_product_map` requires.
- Nothing is matched without a click: one row's tick, a picked product, or "accept the sure ones". Matching sends nothing, as the old map form did.
- A connector reads its catalogue a page at a time (`remote_products`); the screen reads pages in a loop with a progress bar, so a 10,000-product store needs no long request.

## What remains?
1. ~~Remote catalogue, suggestions and the review screen for n11.~~ Done #1880 (`commerce/marketplace-bulk-matching.md`).
2. ~~Shop view: every shop product with its picture and state, codes in the row, bulk actions; the account screen's card a summary.~~ Done #1931 (`commerce/marketplace-shop-view.md`).
3. ~~`marketplace_product_map.remote_code` to `utf8mb4_bin` (schema step 11.0.44), the order import's single-mapping case-blind fallback, the shop view's search case-blind.~~ Done #2095 (`commerce/marketplace-order-import.md`).
4. ~~Listings on the same screen: article cards with pictures, category search by path, one category for several articles, required attributes first and optional ones folded; the old listing card goes.~~ Done #2066 (`commerce/marketplace-listing-view.md`).
5. ~~Trendyol `remote_products` (its product filter service, barcode as the key), so Trendyol accounts get the same suggestions.~~ Done #2128 (`commerce/trendyol-remote-products.md`).

## Open questions?
- Whether n11 products sent from the n11 warehouse (`sender=N11`) should be read too; step 1 reads the default (the seller's own).

## Where to start?
- Every step is done. Once the owner answers the open question, move it into `commerce/n11-api-facts.md` (or a step for it) and delete this plan.
