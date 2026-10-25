---
name: storefront-conversion-pack
description: Plan (approved 2026-10-19; schema, verified reviews, rating, store reply, review e-mail, wishlist core and Favourites page done): designer tokens, heart next; back-in-stock, bundles later
---

## Why?
- Reviews were page comments with stars (no average, verified buyer or request e-mail); no wishlist, back-in-stock or bundles. From 1 Aug 2026 Turkish advertising rules allow only verified-purchaser reviews, kept a year. A fabricated `aggregateRating` was removed; a real average is allowed.

## What is decided?
- (a) Reviews: stay on the `comments` table (no second review model): `verified_order_id`, `seller_reply`, `seller_reply_at`; a product review can be submitted only by a signed-in customer whose order containing the product is `complete` (or delivered). Review request e-mail profile `order_review_request` in `email_campaign_profiles` (day delay after delivery/ship), one per order. Average and count cached on `products.rating_avg/rating_count` (recomputed on publish/delete), `aggregateRating` only from real rows. Reviews are never auto-deleted before 1 year. Catalog widget tokens `__rating_avg`, `__rating_count`, `__reviews` list; legacy `get_catalog.php` unchanged except the JSON-LD helper.
- (b) Wishlist: `wishlist_items` (user or cookie token, product, added_at); catalog hearts, a Favourites page widget, the cookie list merged into the account at sign-in. Owner 2026-10-24: hearts in starter catalog cards and store, boutique, bookshop product cards; no site switch or price-drop e-mail now (no schema change).
- (c) Back in stock: `stock_notify` (product, e-mail or user, phone, sent_at); "Notify me" on out-of-stock items (widget layer); on `inventory.changed` from site writers (not imports, ERP counts) a listener mails (SMS via `sms-and-iys.md`) once per row and marks it sent.
- (d) Bundles (later, own slices): a `bundle` product of other products in fixed quantities; stock from components; price fixed or a discounted sum, charged as a normal line (express-order totals unchanged); lines keep component references for ERP stock moves. After (a)–(c).
- Added in 2a: an unpaid bank transfer is no purchase; one review per purchase; only verified reviews count (28/B(6)); aggregateRating only where starred reviews show: `commerce/product-reviews-verified.md`.

## What is done?
1. Schema 11.0.14 (PR #698) `upgrade_2026_11_0_conversion_pack()`: comments/products columns, `wishlist_items`, `stock_notify`, `order_review_request` in both action enums.
2a. (PR #698) Review gate (comment form, `add_comment.php`), "Verified purchase" badge, rating refreshed on writes and daily, `aggregateRating` in legacy detail markup, store reply (moderation, under the review) (`includes/fn/reviews.php`).
2b. (PR #1316) `order_review_request` e-mail once per order when an account's order ships, cancelled on delete; verified filter in `view_comments.php` (`commerce/product-review-request-email.md`).
3a. (PR #1583) Wishlist core: owners, visitor cookie, `wishlist.php`, merge at sign-in, privacy export, trim (`commerce/wishlist.md`).
3c. (PR #1585) Favourites widget and page, My Account link (`designer/wishlist-widget.md`).

## What remains?
2c. Designer: `__rating_avg`, `__rating_count`, `__reviews` tokens and a review form on designer product pages (comments are page-level there), then aggregateRating in `_pg_build_product_jsonld()` (M).
3b. Heart in catalog widgets, state from `wishlist.php?action=state` (S–M).
4. Back-in-stock capture + listener + templates (S–M).
5. API: `/products/{id}/reviews`, `/customers/{id}/wishlist`, webhook `product.review.created`, `verified` on comments (S).
6. Bundles design note before coding (owner review).

## Open questions?
- Photo reviews: comments take attachments; allow by a setting?
- Featured comments show first; 28/B(4) asks for an objective order. Change it for product reviews?

## Where to start?
- Step 2c or 3b; read `commerce/product-reviews-verified.md` or `commerce/wishlist.md`.
