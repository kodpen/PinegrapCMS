---
name: storefront-conversion-pack
description: Plan (approved 2026-10-19, in progress: schema, verified reviews, rating, store reply, review request e-mail, rating tokens done): wishlist next, back-in-stock, bundles later
---

## Why?
- Reviews were page comments with stars (`comments.item_type` product/product_group): no average, no "verified buyer", no request e-mail; no wishlist, back-in-stock or bundles. From 1 Aug 2026 Turkish advertising rules allow only verified-purchaser reviews, kept one year. Fabricated `aggregateRating` was removed on purpose; a real average is allowed.

## What is decided?
- (a) Reviews: stay on the `comments` table (no second review model): `verified_order_id`, `seller_reply`, `seller_reply_at`; a product review can be submitted only by a signed-in customer whose order containing the product is `complete` (or delivered). Review request e-mail profile `order_review_request` in `email_campaign_profiles` (day delay after delivery/ship), one per order. Average and count cached on `products.rating_avg/rating_count` (recomputed on publish/delete), `aggregateRating` only from real rows. Reviews are never auto-deleted before 1 year. Catalog widget tokens `__rating_avg`, `__rating_count`, `__reviews` list; legacy `get_catalog.php` unchanged except the JSON-LD helper.
- (b) Wishlist: table `wishlist_items` (user_id or cookie token, product_id, added_at); heart button in catalog widgets, My Account "Favourites" page widget, merge of cookie list into the account at sign-in, optional "price dropped" e-mail using `price-history-reference-price.md`.
- (c) Back in stock: table `stock_notify` (product_id, e-mail or user_id, phone, created_at, sent_at); "Notify me" on out-of-stock items in the widget layer; site writers fire `inventory.changed` (not imports, ERP counts) — a listener sends e-mail (and SMS via `sms-and-iys.md`) once per row and marks it sent.
- (d) Bundles (later phase, own slice set): a `bundle` product whose lines are other products with fixed quantities; stock derived from components; price fixed or discounted sum; cart and order lines keep component references for ERP stock moves. Not before (a)–(c).
- Nothing changes in express-order totals; bundle pricing enters as a normal line price.
- Added in 2a: purchase, review and aggregateRating rules in `commerce/product-reviews-verified.md`.

## What is done?
1. Schema 11.0.14 (PR #698) `upgrade_2026_11_0_conversion_pack()`: comments and products columns, `wishlist_items`, `stock_notify`, `order_review_request` in both campaign action enums.
2a. (PR #698) Review gate in the comment form and `add_comment.php`, "Verified purchase" badge, rating refresh on every write path plus a daily pass, `aggregateRating` in the legacy catalog detail markup, the store's reply in Comment Moderation and under the review, verified marker in the list (`includes/fn/reviews.php`, `tests/reviews_test.php`).
2b. (PR #1316) `order_review_request` profile: once per order when an account's order ships, links to review forms, cancelled on delete; verified filter in `view_comments.php` (`commerce/product-review-request-email.md`).
2c (tokens). (PR #TBD) `__rating_avg`, `__rating_count`, `has_rating`/`no_rating` in the catalog widgets (`designer/catalog-rating-tokens.md`).

## What remains?
2c. Review form, `__reviews`, designer aggregateRating: after designable-comments slice 20 (M).
3. Wishlist widget tokens, account page, merge logic (M).
4. Back-in-stock capture + listener + templates (S–M).
5. API: `/products/{id}/reviews`, `/customers/{id}/wishlist`, webhook `product.review.created`, `verified` on comments (S).
6. Bundles design note before coding (owner review).

## Open questions?
- Photo reviews: attachments already exist on comments; allow by setting, moderate as today.
- Featured comments are shown first by default; 28/B(4) asks for an objective order. Change that for product reviews?

## Where to start?
- Step 3; read `commerce/product-reviews-verified.md`.
