---
name: comments-widget-decisions
description: Owner decisions behind the comments widget (2026-10-23, #2410) and the PRs that built it (#1461-#2799): kind, shared options, page settings, names, parent_id, featured default, item threads
module: widgets_comments
---

## What did the owner decide (2026-10-23)?
- Comments are a general system widget kind `comments`, drawn from the designer like form_item_view; no forum-only widget or table. Contract: [[comments-widget-contract]].
- Layout and display options live in the widget row (shared on purpose); the comments and every policy setting stay per page (page columns, `allow_new_comments_for_items`). One widget on many pages shows each page's own thread.
- A general "Make a copy for this page" button for every widget kind with other placements.
- A member's new comment on a designed page is stored under the contact's display name; old comments keep their stored name ([[designed-page-comment-names]]).
- `comments.parent_id` plus indexes `(parent_id)` and `(page_id, item_type, item_id, created_timestamp)` in one 2026.11.0 sub-step (approved under rule 5).
- Narrow extraction: only the rule functions of the legacy block moved to `includes/fn/comments.php`; its HTML builder is untouched and checked byte for byte ([[comment-thread-rules]]).
- Featured comments: `featured: 'first'` (pinned on top, others shown) is a new widget's default; `'filter'` keeps the block's behaviour for converted pages ([[comments-widget-conversion]]).
- Product and event threads (#2410): built after the forum template; the widget reads the item in the address on catalog, catalog detail and event view pages, with a canvas state; product reviews are designable.

## Which PRs built it?
- Slices 1-19 and 3b (#1461-#1620): thread checks, counters and submitter mail on moderation, member names, `parent_id` and replies, the shared rule helpers, the widget (read, form, replies, watch and staff controls, pages and permalinks), panel, canvas, Preview, Make designable and back, save warnings, template kinds, comment e-mails. Recent mode: #1619, #1638, #1679; the forum template uses it since 1.1.0 (#1658).
- Item threads: 20a server side (#2678), 20b designer (#2778), 20c e-mails (#2799, [[comments-widget-email-items]]); designer `aggregateRating` needed no code ([[product-reviews-verified]]).
- Topic files: [[comments-widget]] and the `comments-widget-*` files in designer/.
