---
name: designable-comments
description: Plan (1-14 and 3b done; 15-18 next): comments as a designable system widget kind; layout in the widget row, comments and policy per page; replies via parent_id
---

## What did the owner decide (2026-10-23)?
- Comments become a general system widget kind `comments`, drawn from the designer like form_item_view; no forum-only widget or table. Contract: [[designable-comments-contract]].
- The layout and display options live in the widget row (shared on purpose); the comments and every policy setting stay per page (page columns, `allow_new_comments_for_items`). One widget on many pages shows each page's own thread.
- A general "Make a copy for this page" button for every widget kind with other placements.
- A member's new comment on a designed page is stored under the contact's display name (7r); old comments keep their stored name.
- `comments.parent_id` plus indexes `(parent_id)` and `(page_id, item_type, item_id, created_timestamp)` in one 2026.11.0 sub-step (approved under rule 5).
- Narrow extraction: only the rule functions of the legacy block move to `includes/fn/comments.php`; its HTML builder is untouched and checked byte for byte.
- Per-product and per-event threads in the widget are deferred until after the forum template (slice 20, own decision).
- Featured comments: new `featured: 'first'` (pinned on top, others shown) is the default of a new widget; `'filter'` keeps today's behaviour for converted pages.

## What are the slices? (one PR each, in order)
1. Refuse forged threads on designed record pages: `pg_comment_thread_verify()` in new `includes/fn/comments.php`, `add_comment.php`. Done (#1461).
2. Verify product and calendar_event ids on classic pages (same helper). Done (#1498).
3. 7t: edit_comment.php, job.php and the API refresh `submitted_form_info` and mail the submitter on designed record pages. Done (#1475).
3b. One-time data step (owner, 2026-10-23): recount `submitted_form_info` for every (page, record) on designed record pages, no schema. Done (#1525).
4. 7r: member display name (`pg_comment_member_name()`), add_comment.php and the prefill. Done (#1490).
5. Schema: `comments.parent_id` and the two indexes. Done (#1467).
6. add_comment.php accepts replies (`pg_comment_parent_ok()`, `?reply_to=`), API `parent_id`. Done (#1525).
7. Shared rule helpers for the legacy block (settings, allow-new, viewer, rows, `pg_comments_thread_order()`). Done (#1550).
8. `page_id` in `pg_sw_render_context()`; `pg_comments_widget_drawn()` hand-off so the legacy pass skips a page the widget drew. Done (#1558).
9. "Make a copy for this page" button (style_designer.js). Done (#1472).
10. Read-only Comments widget: renderer `includes/fn/widgets_comments.php`, SW_TYPES, tokens, flags, default layout in three places. Done (#1558).
11. The add-comment form in the widget (bindings, CAPTCHA, rating, publish section, frontend twins). Done (#1558).
12. Replies and quotes (layout flat/threaded, `max_depth`). Done (#1567).
13. Watch box and staff controls. Done (#1567).
14. Pagination (`?comments_page`) and permalinks (`?comment=<id>`). Done (#1567).
15. Designer panel: page comment settings shown on the widget, shared Display section.
16. Page columns the designer cannot set yet (attachments, choose a name, intro and closed messages, watchers managed by submitter); template keys.
17. Canvas samples and Preview.
18. "Make designable" / "Back to the fixed block", save warnings, templates may ship the kind. The forum template ([[design-template-forum]]) needs 1-18.
19. Designable comment e-mails (after 18).
20. Deferred: product and event threads in the widget.

## Where to start?
- Owner (2026-10-24): fewer, larger PRs: A = 6 + 3b (#1525); B = 7 (#1550); C = 8 + 10 + 11 (#1558); D = 12 + 13 + 14 (#1567); E = 15 + 16 + 17 + 18; then 19. Each group branch stacks on the one before.
- Coordinate with #1401 (`get_captcha_fields()`), #1382 and #1402 (page cache), #1316 (mail.php).
