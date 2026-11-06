---
name: product-review-purchase-stands
description: A verified product review hides while its order is cancelled or the reviewed item's whole quantity on it is refunded (#7202): pg_review_purchase_stands_sql(), its refreshes
module: reviews
---

## What happens to a review when its purchase falls away?
- Owner decision #7202 (option C, `workflow/decisions-made-product-34.md`): a verified review hides from the product's review thread, the verified filter (Comment Moderation, API `verified`), `rating_avg` / `rating_count` and aggregateRating while its order is not bought (`pg_review_bought_sql()`: cancelled, among others), and while the reviewed item's whole ordered quantity on that order was returned and refunded.
- The row stays, keeps `verified_order_id`, and Comment Moderation's "all" list shows it; a restored order brings it back.

## Which condition decides it?
- `pg_review_purchase_stands_sql($returns)` in `includes/fn/reviews.php`, used by `pg_review_rating_sql()` and `pg_review_verified_filter_sql('yes')`; `$returns` is `pg_review_returns_ready()` (the returns tables exist).
- A deleted order (row gone) counts as bought, so deleting an order wipes no review history.
- Refunded means `order_returns.refunded_at > 0`, not the status: a return closed after its refund keeps the stamp, and a refund the payment service refused clears it.
- The item's lines: `product_id = comments.item_id` for a product, the group's products for a `product_group` review; lines saved for later do not count. The review hides only when every such line's quantity is covered by refunded return items; an order with no line of the item left does not hide it.

## When are the ratings worked out again?
- `process_order_cancellation()` and `pg_order_restore_cancelled()` call `pg_review_refresh_order()` (every product on the order); `pg_return_refund()` refreshes the returned lines' products once the refund stands (also an unclear one).
- Anything else (an order deleted, sites that cancelled before the change) waits for `pg_review_refresh_daily()`.
