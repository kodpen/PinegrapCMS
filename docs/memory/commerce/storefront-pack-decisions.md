---
name: storefront-pack-decisions
description: Lasting decisions of the storefront pack (reviews, wishlist, back in stock, bundles; 2026-10-19, finished with #2902) and where each part is documented
---

## Why was it built?
- Reviews were page comments with stars (no average, verified buyer or request e-mail); there was no wishlist, back-in-stock notice or bundle. From 1 Aug 2026 Turkish advertising rules allow only verified-purchaser reviews, kept a year. A fabricated `aggregateRating` was removed; a real average is allowed.

## What was decided?
- Reviews stay on the `comments` table (no second review model): `verified_order_id`, `seller_reply`, `seller_reply_at`. Only a signed-in customer whose order holding the product is complete (or delivered) may review; one review per purchase; an unpaid bank transfer is no purchase; only verified reviews count (28/B(6)). Average and count cached on `products.rating_avg` / `rating_count`; `aggregateRating` only from real rows, where starred reviews show. Reviews are never deleted automatically before a year. Legacy `get_catalog.php` unchanged but the JSON-LD helper.
- Review request e-mail: profile `order_review_request` (a day delay after delivery or shipping), one per order.
- Wishlist: `wishlist_items` (user or cookie token); guests open their list before signing in, the cookie list merges at sign-in; a `wishlist` widget page linked from My Account; hearts in starter catalog cards and store, boutique, bookshop product cards; no site switch or price-drop e-mail.
- Back in stock: `stock_notify`; "Notify me" on out-of-stock items; on `inventory.changed` from site writers (not imports or ERP counts) a listener mails or texts once per row.
- Photo reviews by a site setting, off by default, images only; reviews newest first, verified only (#2426).
- Bundles: a product of other products in fixed quantities, stock from components, price fixed or a discounted sum, charged as a normal line (#2425).

## Where is each part?
- Reviews: `product-reviews-verified.md`, `product-review-request-email.md`, `product-review-order.md`, `product-review-photos.md`, `product-review-store-reply.md`; tokens `designer/catalog-rating-tokens.md`; widget `designer/comments-widget-product-page.md`.
- Wishlist: `wishlist.md`, `wishlist-heart.md`. Back in stock: `back-in-stock-notices.md`, `back-in-stock-sms.md`. API: `api/api-reviews-and-wishlist.md`.
- Bundles: `product-bundles.md`, `product-bundles-storefront.md`, `erp/erp-bundle-lines.md`, `api/api-product-bundles.md`.
