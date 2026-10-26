---
name: trendyol-listing-update
description: Updating approved Trendyol listings by contentId (content-bulk-update): what is sent, queue operation listing_update, result by content id, the shop view's tick boxes, commission at approval
---

## Which call changes an approved listing?
- `POST /integration/product/sellers/{sellerId}/products/content-bulk-update`, body `{"items":[{"contentId","title","description","images":[{"url"}]}]}`, at most 1000, answers `batchRequestId`; the result is read with the batch-requests call like a create. Docs "Product Update - Approved Product v2" (v2.0 page, modified 2026-10-12, read 2026-10-26).
- Partial update: a field left out stays as it is, a field sent as null is deleted. Attributes are never sent: naming one means sending all of them, and barcode, productMainId, brandId, categoryId and variant attributes cannot change. Price and stock stay on the price-and-inventory call.
- Not used: `variant-bulk-update`, `delivery-info-bulk-update`.

## What is sent? (`mp_trendyol_update_listings()`, `mp_trendyol_update_body()`)
- One item per content id (the variants of a listing share it; the first queued product speaks). `contentId` a JSON number after `mp_trendyol_id_fits()`. Title, description and pictures chosen as for create (`mp_listing_update_item()`): own title else the article's; full description, else the article's, else the short one. Title cut at 100; description over 30,000 refused; one to eight https pictures.
- A listing the body cannot carry is left out and named in `data['refused']` (content id => reason); its queue lines fail on that reason. Nothing left → no call, `task_id` ''.

## How does it go through the queue?
- Operation `listing_update` (`marketplace_sync_queue.operation` is VARCHAR(30): no schema change). Queued only from the shop view of `marketplace_products.php` for that account (`mp_matches_listing_update()`), never by `mp_enqueue()`; a product already waiting is not queued twice.
- `mp_sync_send_listing_updates()` runs in `mp_sync_push()` after new listings and before the stock batch. Every due line is leased with the call or closed (product gone, no `remote_id` any more, connector cannot), so `mp_sync_close_unmapped()` never closes it as unmatched.
- `mp_sync_apply_results()` matches an update line by the mapping's `remote_id` (`mp_trendyol_batch_results()` reads `requestItem.contentId` when there is no barcode). The verdict closes the line only; the mapping row (status, `last_error`, `last_pushed`) is never written. A failure shows under "What did not go through" marked "Listing changes".

## What does the screen show?
- Shop view of `marketplace_products.php`: tick the products and "Send listing changes" (only the ticked ones with a `remote_id` are queued). "Commission 7.83 %" beside a matched product whose `commission` is set.

## When is the commission written?
- `mp_sync_listing_row_update()` writes `variants[].commission` with the approval that sets `remote_id`, only on a row read with the column (`mp_commission_ready()`), 0 to 9999.99. Approved rows are not read again, so listings approved before this change, or whose rate changes later, keep NULL / the first rate. Re-reading approved rows would crowd the 100-row window of pending ones.

## What is unverified?
- No live call. The result line's key (`requestItem.contentId` assumed: the docs show no result), whether an update sends an approved listing back to review, the title and description limits on update (create's are applied).

Related: docs/memory/commerce/trendyol-listings.md, docs/memory/commerce/trendyol-listing-status.md
