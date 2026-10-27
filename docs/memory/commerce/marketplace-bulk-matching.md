---
name: marketplace-bulk-matching
description: Bulk matching of marketplace products to the shop's (marketplace_products.php, #1880): remote catalogue table, paged read, suggestion scores, sure rule, what a match writes
---

## What is it and why?
- The catalogue view of `marketplace_products.php?account=N` (beside the shop view, `marketplace-shop-view.md`), shown when the connector has `remote_products`. Built because a live n11 store (2026-10-26) could only match one product at a time by typed code, and its n11 codes were not its shop codes. The owner chose a table (not a file) and a screen of its own.
- Each remote product is drawn beside the shop product it most likely is, both with pictures: accept, pick another from a picture grid, mark "not sold here", remove a match, undo; tick many and accept, or accept every sure one.

## Where is the remote catalogue kept?
- `marketplace_remote_products` (2026.11.0, step 11.0.40), one row per account and `remote_code`: what a `marketplace_product_map` row would carry (n11: stock code). Title, picture address (https only), price in minor units as sold there, quantity, their status words, and the suggestion columns (`suggested_product_id`, `suggestion_score`, `suggestion_reason`, `suggestion_sure`) plus `dismissed`.
- `remote_code` is `utf8mb4_bin`: the seller types it, and under `unicode_ci` "ABC-1"/"abc-1" or "ŞAPKA-01"/"SAPKA-01" were one row taking the second product's data under the first's code, so an accepted match sent stock to the wrong listing (review of #1880). The map's `remote_code` became `utf8mb4_bin` too (11.0.44); the helpers still compare in `unicode_ci` and on the bytes, so they work before and after that step: `mp_remote_products_code_join_sql()`, `mp_remote_products_map_code_sql()`, `mp_remote_products_same_code()`.
- Matched is not a column: a row is matched when a map row of its account has exactly its code (`mp_remote_products_matched_sql()`).

## How is it read?
- On the operator's button, never by the job: the script asks `read` for a stretch of about 12 s of pages (`mp_remote_products_read()`), shows the progress and asks again from `next` with the same `run` (the read's start). Rows are upserted with `fetched_timestamp = run`; the read that reaches the last page deletes the rows it did not see and works out the suggestions (`mp_remote_products_finish()`). A failed page keeps what was stored; "Continue reading" goes on from it.
- n11: `GET /ms/product-query?page=N&size=100` (`n11-api-facts.md`); Trendyol: `trendyol-remote-products.md`.

## How are suggestions scored? (`includes/api/outbound/matching.php`, pure, `tests/marketplace_matching_test.php`)
- Same barcode 100, same code once case and separators are dropped 98, one code inside the other or the code as a word of the other title 88-90, else the title: weighted words (rare words weigh more, words with a digit 1.6x), 65% how much of the shop name is in theirs plus 35% overall likeness; a number on each side the other lacks (H4 / H7) x0.7; the price only nudges (+4 close, -8 far). Below 50 nothing is offered.
- Text is transliterated before it is lowercased (dotted İ), the suffix after an apostrophe dropped (12V'li), a number and its unit made one word (12 volt = 12v), decimals kept apart (1,5 mm is not 15 mm).
- One to one: the closest pair anywhere is settled first; already matched shop products are never offered. Sure = score 85+ and 10+ ahead of the row's next candidate (one taken by another row counts too).
- The shop name read is `short_description` (the name the shop shows), else `title` (`mp_remote_products_shop_name()`); `title` is mostly empty.
- The picker's "Closest products" asks the same matcher without the one-to-one rule and with a floor of 15.

## What does a match write?
- A `marketplace_product_map` row (`INSERT IGNORE`), refused when the product or the code is already matched on the account. Nothing is queued or sent; other rows offered the same product lose that suggestion.
- 10,000 x 10,000: about 4 s, 60 MB.

Related: docs/memory/commerce/marketplace-outbound-layer.md
