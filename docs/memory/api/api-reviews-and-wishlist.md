---
name: api-reviews-and-wishlist
description: Reviews and favourites in the external API: verified and store_reply on Comment, GET /products/{id}/reviews, rating on Product, GET /customers/{id}/wishlist, product.review.created
---

## What does a Comment say about a review?
- `verified` (bool): `comments.verified_order_id > 0`, a review from a verified purchase; `store_reply` / `store_reply_at`: the store's answer from the moderation screen, both null without words (`api_comment_review_values()`, PR #1676). The columns (11.0.14) are read behind `pg_reviews_ready()` / `pg_review_replies_ready()`; before them every comment reads false / null.
- `GET /comments?verified=true|false` uses the moderation screen's filter (`pg_review_verified_filter_sql()`). The reply is read only; it is written on the moderation screen.

## Where are a product's reviews?
- `GET /products/{id}/reviews` (`comments:read`, not `products:read`: it is what visitors wrote): 404 for a product that is not there (a binned one answers), then `api_comments_list()` with `item_type=product`, the id, `status=published`, and `verified`, `limit`, `cursor` passed on. Only the product's own reviews, as its rating counts them; a select group's stay under `GET /comments?item_type=product_group`.
- Product carries `rating: {average, count}` (`api_product_rating()`): `products.rating_avg` to two decimals, null while `rating_count` is 0; `api_product_select()` reads zeros before 11.0.14.

## Where are a customer's favourites?
- `GET /customers/{id}/wishlist` (`customers:read`): `api_customer_reachable_or_fail()` first, so the user role's contact-group rule applies (404 outside, `api-user-role-rows.md`); then `wishlist_items` of the accounts whose `user.user_contact` is the contact, newest first, cursor by row id (`api_customer_wishlist_sql()`). A visitor's cookie list names nobody and shows once it joins the account at sign-in ([[wishlist]]). Empty before the table.
- `WishlistItem` (`api_wishlist_item_present()`): `product_id`, `sku` (the id, as on Product), `name`, `short_description`, `available` (`enabled = 1`; a binned product is switched off), `added_at`, `added_at_unix`. A deleted product's row is removed with it (`pg_wishlist_forget_product()`); a row whose product is missing reads null names, available false.

## When is product.review.created sent?
- `add_comment.php`, right after the INSERT, for a comment the review gate covers (`pg_review_gate_applies()`: a product, or a select group of options, once 11.0.14 ran), published, held or scheduled. Staff writing as the store announce it too, `verified: false`.
- Payload (`pg_review_created_event()`, `includes/fn/reviews.php`): `id`, `item_type`, `item_id`, `rating` (null without stars), `verified`, `status` (published, pending, scheduled: the names of `GET /comments`). A later publication, the store's reply and a removal are not announced; the catalogue line says so.

## How is it tested?
- `tests/api_reviews_wishlist_test.php`; `tests/api_customers_test.php` counts seven customers routes, each applying the group rule.
