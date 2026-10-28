---
name: product-review-order
description: How a product's review thread is read (2026.11.0, decided in #2426): verified purchases only, newest first, no featured count, filter or pinning; what it hides
module: comments
---

## Which comments does a product's review thread show?
- Only verified purchases: `pg_comments_rows()` adds `pg_review_verified_filter_sql('yes')` (`comments.verified_order_id > 0`) to the count and the rows when the thread is a product review (`pg_review_gate_applies()`; tests pass `'review' => true|false` in `$options`).
- Hidden there: comments from before the 11.0.14 columns and staff comments under a product (both keep `verified_order_id = 0`). They stay in the table and in Comment Moderation. Ticari Reklam Yönetmeliği 28/B(6): only verified reviews may be shown; decision 2a of #2426 accepts the loss.
- Staff read unpublished reviews as before, still verified only.

## In which order?
- Newest first (`ORDER BY comments.created_timestamp DESC`), 28/B(4): an objective order, the store may not put the reviews it likes first. Other threads stay oldest first.
- No featured count statement is sent (two statements, not three); `has_featured` and `featured_view` are false, so the classic block draws no Show All / Featured switch. The result carries `'review' => true`.
- The comments widget keeps the order read for such a thread: it sets its `order` to `oldest` (no reversal) and `featured` to `none` (no pinning, badge or featured link) whatever its settings say. It draws a product's thread on a classic catalog detail page since slice 20a, with the review form's gate ([[comments-widget-item-threads]]).

## What agrees with it?
- `products.rating_avg` / `rating_count` already count only published, starred, verified reviews (`pg_review_rating_sql()`), so the stars never count a comment the list hides. The list may hold more rows than `rating_count`: a verified review without stars.

## What is left?
- `get_page_content.php`'s comment above its `pg_comments_rows()` call still says "oldest first"; a later PR that touches that file corrects it.
