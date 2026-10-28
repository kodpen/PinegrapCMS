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
3-4. (PRs #1583-#1585, #1659, #1664, #1864) Wishlist, hearts, Favourites; back-in-stock e-mail, "Notify me", SMS (`commerce/wishlist*.md`, `commerce/back-in-stock-*.md`).
5. (PR #1676) API: reviews, rating, a customer's favourites, `product.review.created`, `verified` (`api/api-reviews-and-wishlist.md`).
2d. (PR #2567) Reviews newest first, verified only (#2426): `commerce/product-review-order.md`.

## What remains?
2c. Review form, `__reviews` (M): the comments widget is the list and form on a `catalog detail` page (#2678, #2799). Templates' product pages are `standard` pages with a catalog_item_view widget: page comments, ungated. Cut:
- 2c1. (PR #2817) Done: `designer/comments-widget-product-page.md`.
- 2c2. (PR #2840) Done: same file.
- 2c3. Photo reviews (#2426 1a): decided in #2818 (a): `config.review_photos`, only it allows an image on a review thread.
6. Bundles: `storefront-bundles-design.md`; owner review (#2425).

## Which questions are answered? (#2426)
- Photo reviews by a site setting, off by default, images only. Review order: step 2d.

## Where to start?
- Step 6 after #2425; 2c3 (schema step first).
