---
name: product-review-store-reply
description: The store's reply under a product review (comments.seller_reply, seller_reply_at; 2026.11.0, PR #698): where it is written and shown, when its date is set, the verified marker in moderation
---

## How does the store answer a review?
- Comment Moderation (`edit_comment.php`) shows a product review's order ("Verified purchase: order 1172", linked for e-commerce staff) or "Not a verified purchase", and a "Reply from the store" field. `seller_reply` is kept trimmed, at most 5000 characters; `seller_reply_at` is set when the reply is written or changed and kept while it stays the same (`pg_review_reply_time()`), 0 once cleared.
- The page prints it under the review in both markups (`pg_review_reply_html()`: text escaped, the date as `get_relative_time()`'s `<time>` element). `view_comments.php` marks verified reviews.

## Why is it there?
- Plan `storefront-conversion-pack` (a): the store answers in the review area rather than in a second comment, so the answer stays with the review it is about.
- The answer window of Ticari Reklam Yönetmeliği 28/C (48 hours since RG 33297) is not modelled: nothing holds a review back for the reply. Not checked with a lawyer.
- Only product reviews get the field (`pg_review_gate_applies()`); a page or form-record comment does not. Who reviews: `commerce/product-reviews-verified.md`.
