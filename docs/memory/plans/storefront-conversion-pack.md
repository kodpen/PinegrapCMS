---
name: storefront-conversion-pack
description: Plan (approved 2026-10-19, not started): verified-buyer product reviews with request e-mail and real aggregateRating, wishlist, back-in-stock notice, bundles as a later phase
---

## Why?
- Reviews are generic page comments with a 1–5 star column (`comments.item_type` product/product_group) with no average, no "verified buyer", no request e-mail; wishlist and back-in-stock do not exist; bundles do not exist. From 1 Aug 2026 Turkish advertising rules allow only verified-purchaser reviews and require keeping them one year. Fabricated `aggregateRating` JSON-LD was removed on purpose; a real average is allowed.

## What is decided?
- (a) Reviews: stay on the `comments` table (no second review model): add `verified_order_id`, `seller_reply`, `seller_reply_at`; a product review can be submitted only by a signed-in customer whose order containing the product is `complete` (or delivered). Review request e-mail profile `order_review_request` in `email_campaign_profiles` (day delay after delivery/ship), one per order. Average and count per product cached on `products.rating_avg/rating_count` (recomputed on publish/delete) and printed as `aggregateRating` JSON-LD only from real rows. Retention: reviews are never auto-deleted before 1 year. Catalog widget tokens `__rating_avg`, `__rating_count`, `__reviews` list; legacy `get_catalog.php` unchanged except the JSON-LD helper.
- (b) Wishlist: table `wishlist_items` (user_id or cookie token, product_id, added_at); heart button in catalog widgets, My Account "Favourites" page widget, merge of cookie list into the account at sign-in, optional "price dropped" e-mail using `price-history-reference-price.md`.
- (c) Back in stock: table `stock_notify` (product_id, e-mail or user_id, phone, created_at, sent_at); "Notify me" on out-of-stock items in the widget layer; the inventory writers already fire `inventory.changed` — a listener sends e-mail (and SMS via `sms-and-iys.md`) once per row and marks it sent.
- (d) Bundles (later phase, own slice set): a `bundle` product whose lines are other products with fixed quantities; stock derived from components; price fixed or discounted sum; cart and order lines keep component references for ERP stock moves. Not before (a)–(c).
- Nothing changes in express-order totals; bundle pricing enters as a normal line price.

## What remains?
1. Migration step (comments columns, products cache columns, wishlist, stock_notify) (S).
2. Review gate + request profile + reply UI in Comment Moderation + JSON-LD (M).
3. Wishlist widget tokens, account page, merge logic (M).
4. Back-in-stock capture + listener + templates (S–M).
5. API: `/products/{id}/reviews`, `/customers/{id}/wishlist`, webhook `product.review.created`; tests for the gate and the average (S).
6. Bundles design note before coding (owner review).

## Open questions?
- Photo reviews: attachments already exist on comments; allow by setting, moderate as today.

## Where to start?
- Step 1 and 2; read `docs/memory/platform/structured-data-open-graph.md` and `designer/catalog-rss-structured-data.md` first.
