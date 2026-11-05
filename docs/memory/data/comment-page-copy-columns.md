---
name: comment-page-copy-columns
description: Which comment columns Duplicate Page writes on the copied comments (stars, store answer, question kind) and why verified_order_id stays 0.
module: reviews
---

## Which columns does a copied comment keep?
- `duplicate_page_f.php` copies a page's comments oldest first with a column list. Besides the classic columns and `parent_id` ([[comment-replies]]) it writes `rating`, and through `pg_comment_copy_columns()` (`includes/fn/reviews.php`, pure) `seller_reply` / `seller_reply_at` once `pg_review_replies_ready()`, and `kind` / `answer_of` once `pg_product_questions_ready()`.
- `answer_of` names the copy of the question, mapped like `parent_id` (`pg_comment_copy_parent_id()`); an answer whose question was not copied gets 0. An unknown kind is written as `comment`.

## What did the copy do before?
- It dropped those columns (PR #NNNN): a copied review lost its stars and the store's answer, a copied question or answer became a plain comment.

## Why is `verified_order_id` not copied?
- A product's rating counts published, verified reviews over every page (`pg_review_rating_sql()`), and the review gate counts reviews per order. A copy marked verified would count the same purchase twice, so the copy stays 0 (unverified).

## How is it checked?
- `tests/comment_copy_columns_test.php`: the helper and the copy's wiring.
