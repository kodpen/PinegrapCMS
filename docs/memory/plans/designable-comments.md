---
name: designable-comments
description: Plan (1-19 and 3b done; 20 open, waits for the owner): comments as a designable system widget kind; what slice 20 (product and event threads) needs and where to start
---

## What did the owner decide (2026-10-23)?
- Comments become a general system widget kind `comments`, drawn from the designer like form_item_view; no forum-only widget or table. Contract: [[designable-comments-contract]].
- The layout and display options live in the widget row (shared on purpose); the comments and every policy setting stay per page (page columns, `allow_new_comments_for_items`). One widget on many pages shows each page's own thread.
- A general "Make a copy for this page" button for every widget kind with other placements.
- A member's new comment on a designed page is stored under the contact's display name; old comments keep their stored name.
- `comments.parent_id` plus indexes `(parent_id)` and `(page_id, item_type, item_id, created_timestamp)` in one 2026.11.0 sub-step (approved under rule 5).
- Narrow extraction: only the rule functions of the legacy block move to `includes/fn/comments.php`; its HTML builder is untouched and checked byte for byte.
- Per-product and per-event threads in the widget are deferred until after the forum template (slice 20, own decision).
- Featured comments: `featured: 'first'` (pinned on top, others shown) is the default of a new widget; `'filter'` keeps today's behaviour for converted pages.

## What is done?
- Slices 1-19 and 3b, one PR each (#1461-#1620): thread checks on designed and classic pages, counters and submitter mail on moderation, member names, `parent_id` and replies, the shared rule helpers, the widget (read, form, replies and quotes, watch and staff controls, pages and permalinks), its panel, canvas and Preview, Make designable and back, save warnings, template kinds, comment e-mails. The recent mode followed (#1619, #1638, #1679), and the forum template uses the widget since 1.1.0 (#1658). Topic files: [[comments-widget]] and the `comments-widget-*` files in designer/.

## What is left?
- 20. Product and event threads in the widget. Today `pg_comments_widget_classic_thread()` leaves a `catalog` (product_group), `catalog detail` (product, product_group) or `calendar event view` (calendar_event) page without a top-level record view to the classic block, which picks the item in the address (`pg_comment_classic_item_types()`, [[comments-widget-hand-off]]).
- It needs: the thread key from the address as the classic block reads it (product or group id, event id and recurrence); `pg_comment_thread_verify()` already checks those ids (slice 2); the review rules of a product thread (verified purchase, store reply, rating recount `pg_review_comment_changed()`: `commerce/product-reviews-verified.md`, `product-review-store-reply.md`); tokens and flags for them, the canvas samples and the panel; the search index's per-event comments; then `pg_comments_widget_classic_thread()` hands those pages to the widget, and Make designable offers them.
- Size: more than one PR; cut it into steps of about 1,000 product lines when the owner says go.

## Open question for the owner
- Build slice 20, or drop it and keep the classic block on product and event pages? If dropped: move the decisions above to designer/ (with that answer) and delete this plan.

## Where to start?
- Ask the owner the question above first. If it is a go: read [[comments-widget-hand-off]] and [[designable-comments-contract]], then write the steps of slice 20 into this file before the first draft PR.
