---
name: marketplace-shop-view
description: Shop view of marketplace_products.php (#1931): shop products with pictures and their state on an account, codes typed in the row, bulk own-code match, send, remove, listing changes
---

## What is it and why?
- `marketplace_products.php?account=N&mode=shop`: every shop product outside the Recycle Bin with its picture, name, code, the price and quantity it is sent with, and on the account its code, listing state, commission, last send and refusal. Filters: all, matched, not matched, not sent (`status = 'error'`, listed first). Works for every provider; Trendyol, which cannot list its catalogue, gets only this view.
- It replaced the account screen's list of matched products, its "Match a product" modal (a pictureless datalist picker) and the `map`, `unmap`, `listing_update` actions (owner asked for a picture-led interface, 2026-10-26). That card now gives the shop view's counts and links here.

## How is a product matched here? (`includes/api/outbound/product_matches.php`)
- "Match with its own code": the product's code, or on a barcode-keyed provider its first barcode (`mp_matches_own_code()`); a product without one only gets "Type a code".
- "Type a code" / "Change code": an inline box in the row; empty means its own code. `mp_matches_set_code()` refuses a code another product of the account holds (byte for byte, `mp_remote_products_map_code_sql()`); matching again clears `remote_id` (`trendyol-listing-status.md`); a catalogue row suggesting the product loses it.

## What about matches of deleted or binned products?
- Product delete (`pg_pb_delete_product()`) leaves the map row. No view lists it, yet it held its code: a new product with the barcode was refused for ever (review of #1931). `mp_matches_set_code()` now removes the deleted products' rows on that code first (`mp_matches_clear_stale($account, $code, false)`); a binned holder is refused with a message naming the Recycle Bin.
- The account card counts live matches (as the shop view) and, when there are any, the stale ones (deleted or binned) with "Remove them" (action `clear_stale_matches`).
- Bulk on the ticked rows: match with own codes, send now, send listing changes (where the connector has `update_listings`), remove match (confirm; nothing changes on the marketplace). A redrawn row keeps its tick.
- Send now (`mp_matches_send()`, also "Send all now") queues this account only, active matches only, priority 1, and leaves a waiting row as it is: `mp_enqueue()` queued every account the product is on and pulled waiting rows past their lease and retry wait. A refused match has no "Send now"; a paused account says so.

## What does a row show? (`mp_matches_listing_state()`, `mp_matches_quantity()`)
- State, with `listing_status`: on (has `remote_id`), rejected (`last_error` starts with `mp_listing_rejected_prefix()`), not sent (other error), awaiting approval; without it only "not sent" is shown.
- Quantity: 0 when stock is not tracked, the product is off or flagged out of stock, as `mp_product_row()` sends.
- Tests: `tests/marketplace_product_matches_test.php`; the old card's source tests moved to the new files (`marketplace_trendyol_listing_status_test.php`, `catalog_bin_test.php`).

Related: docs/memory/commerce/marketplace-bulk-matching.md
