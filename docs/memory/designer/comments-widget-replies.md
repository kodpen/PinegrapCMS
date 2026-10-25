---
name: comments-widget-replies
description: Replies in the comments widget: order units, quotes, reply/cancel/share on links and buttons, the kept parent_id and ?reply_to=, pg_comment_reply_ok, init_comment_actions
---

## In which order are comments drawn?
- `pg_comments_widget_order()` over `pg_comments_thread_order($rows, layout, max_depth)`. A unit is a depth-0 entry with the replies drawn under it (flat: each comment is one). `newest` turns units around, replies stay oldest first; featured `first` pins units whose top-level comment is featured.
- `quoted`: flat, every reply whose comment is among the rows; threaded, only a reply capped at max_depth (drawn no deeper than its comment).

## Which tokens and flags say where a comment stands?
- `pg_comments_widget_link_values()`: `__comment_reply_url` (`?reply_to=<id>#software_add_comment`, '' when the visitor may not answer), `__comment_parent_id|name|url` (url `#c-<id>` on this page, else its permalink; name Anonymous when empty), `__comment_quote` (`pg_comments_quote_excerpt()`: folded, 160 characters, cut at a word, `…`), `__comment_depth`, `__comment_reply_count`, `__comment_url` ([[comments-widget-pages]]). Thread: `__reply_to_name`.
- Flags: `comment_is_root|is_reply` (depth as drawn; flat is always root), `comment_has_quote`, `comment_has_replies`, thread `is_replying`. Row root: `data-pg-comment-depth="N"` after `id="c-N"`.

## How do reply, cancel and share work?
- On a link or a button only (`_pg_comments_wire_link_or_button()`), else dropped. Links work without script (reply to `?reply_to=`, share opens the permalink), rel nofollow; a button gets type=button. Outside the loop_area reply and share are dropped, cancel inside it; the canvas fades them ([[comments-widget-watch-staff]]).
- Reply carries `data-pg-comment-reply|-name` and is wrapped in a root node shown by the internal row flag `pg_comment_reply_ok`, kept out of the flag literals (check_bindings would call it UNLISTED). It is `pg_comment_parent_ok()` for the row: the thread the form posts to (`pg_comments_rows()` option `thread_key`; a page read holds every item's comments) and visible to the visitor.
- `software.init_comment_actions()` (frontend.src.js, hand-made min twin), delegated clicks: reply fills `input[name="parent_id"]` and `[data-pg-comment-reply-to-name]`, unhides `[data-pg-comment-replying]`, focuses the message; cancel sets 0 and hides; share copies (clipboard in a secure context, "Link copied." for 2 s) or `window.prompt()`s.
- It also locks the submit buttons of `form#software_watcher`, `form#software_allow_or_disallow_new_comments` and `.pg-comment-watchers form` once sent (a double click on Notify me ended on the handler's "already a watcher" error page), and a `pageshow` from the back/forward cache unlocks them and the comment form's.

## Which comment does the form answer?
- `pg_comment_reply_to()` on the add_comment state's `parent_id` when the state is in session (a refused post keeps it, a refused parent empties it), else on `?reply_to=`; only with `can_comment` and `pg_comment_replies_ready()` (before 11.0.31: no reply control, no quote).
- While the visitor may answer, a semantic `is_replying` box stays with `data-pg-comment-replying` and `hidden` (`pg_comments_mark_reply_boxes()`) for the script; a content node keeps the flag rule. A `d-*` class beats Bootstrap's `[hidden]`: the starter's `d-flex` box shows, empty.
- Starter: Reply Indent (`comment_is_reply`), Quote, Comment Actions (Reply, Share links), and Replying To with Cancel after the form's Messages.

## How was it checked?
- `tests/comments_widget_test.php` (threaded order, quote, link values, row and control bindings, reply boxes, draw).
- Sandbox, MariaDB, PHP 8.3 (2026-10-24): threaded max_depth 2 (a depth-3 reply capped and quoting), reply by `?reply_to=` and through the form, refused empty post keeping its parent, refused hidden parent, another item's comment without a reply link; Chromium with each frontend twin: reply link and button, cancel, share copied.
