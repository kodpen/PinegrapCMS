---
name: storefront-conversion-pack
description: Plan (approved 2026-10-19; reviews, review e-mail, rating tokens, wishlist, back-in-stock e-mail, form and SMS, API done): review form, bundles
---

## Why?
- Reviews were page comments with stars (no average, verified buyer or request e-mail); no wishlist, back-in-stock or bundles. From 1 Aug 2026 Turkish advertising rules allow only verified-purchaser reviews, kept a year. A fabricated `aggregateRating` was removed; a real average is allowed.

## What is decided?
- (a) Reviews: stay on the `comments` table (no second review model): `verified_order_id`, `seller_reply`, `seller_reply_at`; a product review can be submitted only by a signed-in customer whose order containing the product is `complete` (or delivered). Review request e-mail profile `order_review_request` in `email_campaign_profiles` (day delay after delivery/ship), one per order. Average and count cached on `products.rating_avg/rating_count`, `aggregateRating` only from real rows. Reviews are never auto-deleted before 1 year. Catalog widget tokens `__rating_avg`, `__rating_count`, `__reviews` list; legacy `get_catalog.php` unchanged but the JSON-LD helper.
- (b) Wishlist: `wishlist_items` (user or cookie token, product, added_at); catalog hearts, a Favourites page widget, the cookie list merged into the account at sign-in. Owner 2026-10-24: guests open their list before signing in; a `wishlist` widget page linked from My Account; hearts in starter catalog cards and store, boutique, bookshop product cards; no site switch or price-drop e-mail now (no schema change).
- (c) Back in stock: `stock_notify` (product, e-mail or user, phone, sent_at); "Notify me" on out-of-stock items (widget layer); on `inventory.changed` from site writers (not imports, ERP counts) a listener mails or texts once per row and marks it sent.
- (d) Bundles (own slices): a `bundle` product of other products in fixed quantities; stock from components; price fixed or a discounted sum, charged as a normal line (express-order totals unchanged); lines keep component references for ERP stock moves. After (a)–(c).
- Added in 2a: an unpaid bank transfer is no purchase; one review per purchase; only verified reviews count (28/B(6)); aggregateRating only where starred reviews show: `commerce/product-reviews-verified.md`.

## What is done?
1. Schema 11.0.14 (PR #698): comments/products columns, `wishlist_items`, `stock_notify`, `order_review_request` in the action enums.
2a. (PR #698) Review gate, "Verified purchase" badge, rating refresh, legacy `aggregateRating`, store reply (`includes/fn/reviews.php`).
2b. (PR #1316) Review request e-mail, verified filter (`commerce/product-review-request-email.md`).
2c (tokens). (PR #1560) Rating tokens and flags in the catalog widgets (`designer/catalog-rating-tokens.md`).
3a. (PR #1583) Wishlist core (`commerce/wishlist.md`).
3b. (PR #1584) Catalog hearts, `pg_wishlist.js` (`commerce/wishlist-heart.md`).
3c. (PR #1585) Favourites widget and page, My Account link (`designer/wishlist-widget.md`).
4a. (PR #1659) Back-in-stock requests, the `inventory.changed` listener and e-mail (`commerce/back-in-stock-notices.md`).
4b. (PR #1664) "Notify me" field and button in the catalog item view, `can_notify`, starter (`designer/catalog-stock-notify-form.md`).
4c. (PR #1864) SMS, kind `stock` (owner: "sadece işlem mesajları yer alacak"): `commerce/back-in-stock-sms.md`.
5. (PR #1676) API: reviews, rating, a customer's favourites, `product.review.created`, `verified` (`api/api-reviews-and-wishlist.md`).
2d. (PR #2567) Reviews newest first, verified only (#2426): `commerce/product-review-order.md`.

## What remains?
2c. Review form, `__reviews`, designer aggregateRating: after designable-comments slice 20 (M).
6. Bundles: note in `storefront-bundles-design.md`; owner review first.

## Open questions?
- Photo reviews: comments take attachments; allow by a setting?

## Where to start?
- Step 6: owner answers `storefront-bundles-hooks.md` questions, then its step 1. 2c waits for designable-comments slice 20.
