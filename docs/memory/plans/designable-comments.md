---
name: designable-comments
description: Plan (1-19, 3b, 20a and 20b done; 20c open): comments as a designable system widget kind; the steps of slice 20 (product and event threads) and where to start
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
- 20. Product and event threads in the widget, in steps of about 1,000 product lines:
  - 20a (server side, done #2678): `pg_comments_widget_classic_thread()` keeps only `form item view`; the widget reads the item in the address (`pg_comment_address_item()`), checks it with `pg_comment_thread_verify()`, keeps the review rules (verified only, newest first, the buyer gate), the search index's per-event comments. [[comments-widget-item-threads]].
  - 20b (done #2778): canvas states and samples for a product, a group and an event thread; dedicated tokens and flags (review thread, item name) in the PHP and JS lists together; the panel and Make designable offering catalog, catalog detail and calendar event view pages.
  - 20c (if needed): comment e-mails for item threads (links to the item's address); `aggregateRating` on designer product pages (`commerce/product-reviews-verified.md`).

## What was decided for slice 20? (#2410)
- Build it (M): the widget reads the item in the address on catalog detail and event pages, with a canvas state; product reviews become designable.

## Where to start?
- 20c: read [[comments-widget-item-threads]] and [[comments-widget-email]]; for `aggregateRating`, `commerce/product-reviews-verified.md`.
