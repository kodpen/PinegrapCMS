---
name: storefront-conversion-pack
description: Plan (approved 2026-10-19; schema, verified reviews, rating, store reply, review e-mail, rating tokens, wishlist core, heart, Favourites page done): back-in-stock, bundles later
---

## Why?
- Reviews were page comments with stars (no average, verified buyer or request e-mail); no wishlist, back-in-stock or bundles. From 1 Aug 2026 Turkish advertising rules allow only verified-purchaser reviews, kept a year. A fabricated `aggregateRating` was removed; a real average is allowed.

## What is decided?
- (a) Reviews: stay on the `comments` table (no second review model): `verified_order_id`, `seller_reply`, `seller_reply_at`; a product review can be submitted only by a signed-in customer whose order containing the product is `complete` (or delivered). Review request e-mail profile `order_review_request` in `email_campaign_profiles` (day delay after delivery/ship), one per order. Average and count cached on `products.rating_avg/rating_count`, `aggregateRating` only from real rows. Reviews are never auto-deleted before 1 year. Catalog widget tokens `__rating_avg`, `__rating_count`, `__reviews` list; legacy `get_catalog.php` unchanged but the JSON-LD helper.
- (b) Wishlist: `wishlist_items` (user or cookie token, product, added_at); catalog hearts, a Favourites page widget, the cookie list merged into the account at sign-in. Owner 2026-10-24: guests open their list before signing in; a `wishlist` widget page linked from My Account; hearts in starter catalog cards and store, boutique, bookshop product cards; no site switch or price-drop e-mail now (no schema change).
- (c) Back in stock: `stock_notify` (product, e-mail or user, phone, sent_at); "Notify me" on out-of-stock items (widget layer); on `inventory.changed` from site writers (not imports, ERP counts) a listener mails (SMS via `sms-and-iys.md`) once per row and marks it sent.
- (d) Bundles (later, own slices): a `bundle` product of other products in fixed quantities; stock from components; price fixed or a discounted sum, charged as a normal line (express-order totals unchanged); lines keep component references for ERP stock moves. After (a)–(c).
- Added in 2a: an unpaid bank transfer is no purchase; one review per purchase; only verified reviews count (28/B(6)); aggregateRating only where starred reviews show: `commerce/product-reviews-verified.md`.

## What is done?
1. Schema 11.0.14 (PR #698): comments/products columns, `wishlist_items`, `stock_notify`, `order_review_request` in the action enums.
2a. (PR #698) Review gate, "Verified purchase" badge, rating refresh, legacy `aggregateRating`, store reply (`includes/fn/reviews.php`).
2b. (PR #1316) `order_review_request` e-mail once per order when an account's order ships, cancelled on delete; verified filter in `view_comments.php` (`commerce/product-review-request-email.md`).
2c (tokens). (PR #1560) Rating tokens and flags in the catalog widgets (`designer/catalog-rating-tokens.md`).
3a. (PR #1583) Wishlist core (`commerce/wishlist.md`).
3b. (PR #1584) Catalog hearts, `pg_wishlist.js` (`commerce/wishlist-heart.md`).
3c. (PR #1585) Favourites widget and page, My Account link (`designer/wishlist-widget.md`).

## What remains?
2c. Review form, `__reviews`, designer aggregateRating: after designable-comments slice 20 (M).
4a. Back-in-stock requests (`includes/fn/stock_notify.php`, `stock_notify.php`), e-mail on `inventory.changed`, trim, export (S).
4b. "Notify me" e-mail field and button in the catalog item view, its starters and the shop templates (S).
4c. The notice by SMS, after `sms-and-iys.md` step 3 (S).
5. API: `/products/{id}/reviews`, `/customers/{id}/wishlist`, webhook `product.review.created`, `verified` on comments (S).
6. Bundles design note before coding (owner review).

## Open questions?
- Photo reviews: comments take attachments; allow by a setting?
- Featured comments show first; 28/B(4) asks for an objective order. Change it for product reviews?

## Where to start?
- Step 4a, then 4b; read `commerce/wishlist.md` (the same shape).
