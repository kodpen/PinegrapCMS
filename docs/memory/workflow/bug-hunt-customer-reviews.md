---
name: bug-hunt-customer-reviews
description: Bug hunt of the store's customer reviews (architect, agent channel message 10333, 2026-11-20): the fault fixed, the issue opened, what was read clean
module: reviews
---

## What was the area?
- The store's customer reviews: `includes/fn/reviews.php` and what calls it (`add_comment.php`, `edit_comment.php`, `view_comments.php`, `job.php`, `update_order.php`, the comments API's update and delete, `pg_return_refund()`, the classic block in `get_page_content.php`, the comments widget). The architect named it in agent channel message 10333.
- Not touched: `includes/fn/widgets.php`, `output.php`, `ecommerce.php`, `google_reviews.php`.

## What did it fix? (its PR lists the fault with file and line)
- The review form opened on an order whose lines of the item had all been returned and refunded. That review hid at once (`pg_review_purchase_stands_sql()`). Because it was the newest free order, it was also picked over an older order whose purchase still stood. Now `pg_review_eligibility()` asks `pg_review_order_item_stands_sql()` (`commerce/product-review-purchase-stands.md`).

## What was opened as an issue?
- #10651: a waiting review request e-mail still goes out after every line of the order was returned and refunded. `pg_return_refund()` lives in `includes/fn/returns.php`, outside the area.

## What was read clean?
- The purchase check's bought rule and the newest-free-order choice.
- The purchase-stands condition: cancelled orders, deleted orders, refunded quantity per line, group lines.
- The rating refresh on add (published only), moderation save and delete, quick edit, the scheduled publish, the API's update and delete, cancel and restore, and a refunded return. Also the daily pass and its state file.
- AggregateRating, which is never written without a review behind it.
- The store reply: trimmed, at most 5000 characters, its date kept while the text is unchanged, the text escaped.
- The photo check: name ending plus `getimagesize()` type, run before the file is stored; questions take no file.
- The review request: trigger after `order_shipped`, account and bought rule, once per order under `GET_LOCK`, targets and group choice, mail-merge fields, cancel on cancellation and on delete.
- The moderation filter, which takes only `yes` or `no`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/product-reviews-verified.md
