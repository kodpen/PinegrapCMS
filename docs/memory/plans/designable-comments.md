---
name: designable-comments
description: Plan (approved 2026-10-23, not started): comments as a general designable system widget kind; layout shared in the widget row, comments and policy per page; replies via parent_id; 19 slices
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
1. Refuse forged threads on designed record pages: `pg_comment_thread_verify()` in new `includes/fn/comments.php`, `add_comment.php`.
2. Verify product and calendar_event ids on classic pages (same helper).
3. 7t: edit_comment.php, job.php and the API refresh `submitted_form_info` and mail the submitter on designed record pages.
4. 7r: member display name (`pg_comment_member_name()`), add_comment.php and the prefill.
5. Schema: `comments.parent_id` and the two indexes.
6. add_comment.php accepts replies (`pg_comment_parent_ok()`, `?reply_to=`), API `parent_id`.
7. Shared rule helpers for the legacy block (settings, allow-new, viewer, rows, `pg_comments_thread_order()`).
8. `page_id` in `pg_sw_render_context()`; `pg_comments_widget_drawn()` hand-off so the legacy pass skips a page the widget drew.
9. "Make a copy for this page" button (style_designer.js).
10. Read-only Comments widget: renderer `includes/fn/widgets_comments.php`, SW_TYPES, tokens, flags, default layout in three places.
11. The add-comment form in the widget (bindings, CAPTCHA, rating, publish section, frontend twins).
12. Replies and quotes (layout flat/threaded, `max_depth`).
13. Watch box and staff controls.
14. Pagination (`?comments_page`) and permalinks (`?comment=<id>`).
15. Designer panel: page comment settings shown on the widget, shared Display section.
16. Page columns the designer cannot set yet (attachments, choose a name, intro and closed messages, watchers managed by submitter); template keys.
17. Canvas samples and Preview.
18. "Make designable" / "Back to the fixed block", save warnings, templates may ship the kind. The forum template ([[design-template-forum]]) needs 1-18.
19. Designable comment e-mails (after 18).
20. Deferred: product and event threads in the widget.

## Where to start?
- Slices 1, 3, 5 and 9 touch different files and can run in parallel; 2 after 1, 4 after 3, 6 after 1 and 5, 7 after 4 and 6; 10 after 7 and 8.
- Coordinate with #1401 (`get_captcha_fields()`), #1382 and #1402 (page cache), #1316 (mail.php).
