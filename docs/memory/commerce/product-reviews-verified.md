---
name: product-reviews-verified
description: Product reviews only from buyers (2026.11.0, 11.0.14, PR #698): which comments are reviews, the purchase check, verified_order_id, the product rating and its refresh, aggregateRating
---

## Which comments are product reviews?
- Comments with `item_type = 'product'`, or `'product_group'` when the group's `display_type` is `select` (a product with options): `pg_review_item_is_product()`, `pg_review_gate_applies()` in `includes/fn/reviews.php`. A browsing group's comments are about the category and are not gated.
- They exist only on legacy `catalog detail` (and `catalog`) pages. A visual-designer product page has page-level comments (`item_type = ''`), so nothing there is gated or counted.

## Who may write one, and why?
- Ticari Reklam Yönetmeliği 28/B (RG 33297, in force 2026-08-01): only buyers review; a review whose purchase was not verified may not be published or used in advertising; a refused review is told to its author at once.
- A signed-in customer with a "bought" order holding the item that no review of the item names yet (`pg_review_eligibility()`): status `complete`/`exported`, and not an unpaid bank transfer (`payment_method <> 'Offline Payment' OR paid_at > 0 OR erp_on_account = 1`); cart lines saved for later do not count; a group counts any of its products. Orders by `orders.user_id`, as My Account lists them.
- One purchase opens one review: the order id is kept in `comments.verified_order_id`, so the next order of the item opens the next review.
- Refusals (`pg_review_refusal()`): `sign_in`, `not_bought`, `reviewed`, each with its own sentence (`pg_review_refusal_message()`).
- Staff with edit access to the page are not asked; their comment keeps `verified_order_id = 0` and counts in no rating.

## Where is it checked?
- `get_page_content.php`: the comment form is not drawn for a refused reader; a visitor gets the sign-in link ("Please {var:1} to review a product you bought."), a customer the reason. A verified review shows a "Verified purchase" badge.
- `add_comment.php`: the same check before the INSERT (the form is only a courtesy), with a log line; it writes `verified_order_id` and clamps the stars to 0-5.
- `tests/reviews_test.php` pins the rules and that each write path asks or refreshes.

## How is a product's rating kept?
- `products.rating_avg` (DECIMAL 3,2) and `rating_count`: published, verified, starred (1-5) reviews of the product only (`pg_review_rating_sql()`). Comments from before 11.0.14 are unverified and never count. Group reviews have no rating yet.
- Refreshed by `pg_review_refresh_products()` / `pg_review_comment_changed()` after add (when published), moderation save or delete (`edit_comment.php`), the scheduled publish (`job.php`) and the API update/delete. Paths that delete a page's comments (`edit_page.php`, `edit_pages.php`, the file manager) do not call it: `pg_review_refresh_daily()` in `job.php` recomputes every product once a day (one UPDATE...JOIN writing only changed rows; state in `data/temp/review_ratings_refreshed.txt`).

## When does the markup carry aggregateRating?
- `pg_review_aggregate_jsonld()`: only with at least one review and an average of 1-5; never invented (`platform/structured-data-open-graph.md`).
- Only the legacy catalog detail block of `get_page_content.php`, when the page shows comments with stars (`comments` and `comments_rating` on): markup may describe only reviews a visitor can read there. `_pg_build_product_jsonld()` (designer pages) adds none until the designer shows reviews (plan step: `__reviews` token).

## What is not done yet?
- Seller reply (`seller_reply`, `seller_reply_at` exist, nothing writes them), the `order_review_request` e-mail (enum value exists), a verified filter in moderation, widget tokens, the API: `plans/storefront-conversion-pack.md`.
- By default a page with featured comments shows only those first; 28/B(4) asks for an objective order. Owner question.
