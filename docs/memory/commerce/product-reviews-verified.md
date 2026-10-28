---
name: product-reviews-verified
description: Product reviews only from buyers (2026.11.0, 11.0.14, PR #698): which comments are reviews, the purchase check, the product rating and its refresh, aggregateRating
module: reviews
---

## Which comments are product reviews?
- Comments with `item_type = 'product'`, or `'product_group'` when the group's `display_type` is `select` (a product with options): `pg_review_item_is_product()`, `pg_review_gate_applies()` in `includes/fn/reviews.php`. A browsing group's comments are about the category and are not gated.
- Only on `catalog detail` (and `catalog`) pages, classic block or comments widget, and designed product pages (catalog_item_view, [[comments-widget-product-page]]); a post naming them elsewhere is refused ([[comment-thread-verify]]).

## Who may write one, and why?
- Ticari Reklam Yönetmeliği 28/B (RG 33297, in force 2026-08-01): only buyers review; a review whose purchase was not verified may not be published or used in advertising; a refused review is told to its author at once.
- A signed-in customer with a "bought" order holding the item that no review of the item names yet (`pg_review_eligibility()`): status `complete`/`exported`, and not an unpaid bank transfer (`payment_method <> 'Offline Payment' OR paid_at > 0 OR erp_on_account = 1`); cart lines saved for later do not count; a group counts any of its products. Orders by `orders.user_id`, as My Account lists them.
- One purchase opens one review: the order id is kept in `comments.verified_order_id`, so the next order of the item opens the next review.
- Refusals (`pg_review_refusal()`): `sign_in`, `not_bought`, `reviewed`, each with its own sentence (`pg_review_refusal_message()`).
- Staff with edit access to the page are not asked; their comment keeps `verified_order_id = 0` and counts in no rating.

## Where is it checked?
- The classic block and the comments widget (slice 20a): no comment form for a refused reader; a visitor gets a sign-in link, a customer the reason. A verified review shows a badge.
- `add_comment.php`: the same check before the INSERT (the form is only a courtesy), with a log line; it writes `verified_order_id` and clamps the stars to 0-5.
- `tests/reviews_test.php`: the rules, and that each write path asks or refreshes.

## How is a product's rating kept?
- `products.rating_avg` (DECIMAL 3,2) and `rating_count`: published, verified, starred (1-5) reviews of the product only (`pg_review_rating_sql()`). Comments from before 11.0.14 are unverified and never count. Group reviews have no rating yet.
- Refreshed by `pg_review_refresh_products()` / `pg_review_comment_changed()` after add (when published), moderation save or delete (`edit_comment.php`), the scheduled publish (`job.php`) and the API update/delete. Paths that delete a page's comments (`edit_page.php`, `edit_pages.php`, the file manager) do not call it: `pg_review_refresh_daily()` in `job.php` recomputes every product once a day (state in `data/temp/review_ratings_refreshed.txt`).
- Designer pages: catalog widget tokens `__rating_avg` / `__rating_count`, flags `has_rating` / `no_rating` (`designer/catalog-rating-tokens.md`).

## When does the markup carry aggregateRating?
- `pg_review_aggregate_jsonld()`: only with at least one review and an average of 1-5; never invented (`platform/structured-data-open-graph.md`).
- Only the legacy catalog detail block of `get_page_content.php` (System region), when the page shows comments with stars (`comments`, `comments_rating`): markup describes only reviews a visitor can read there. Also when the comments widget draws them.
- `_pg_build_product_jsonld()`: only where its comments widget shows them ([[comments-widget-product-page]]). A catalog detail page with a catalog_item_view widget and no System region gets no Product markup; not covered.

## Where are the other parts?
- Store reply: [[product-review-store-reply]]. Order: [[product-review-order]]. Request e-mail: [[product-review-request-email]]. API: `api/api-reviews-and-wishlist.md`. Photo reviews: [[product-review-photos]].
