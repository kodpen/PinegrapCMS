---
name: designable-comments-contract
description: Contract of the planned comments widget (2026-10-23): what is stored where, token, flag and control names, thread order, the security rules every slice of designable-comments keeps
---

## What is stored where?
- Widget row (`shared_components`, shared): the tree (comment card, states, form, watch box, staff box, Messages nodes) and `system_region_config` with `regionType 'comments'`, `order` (oldest/newest), `featured` (first/filter/none), `layout` (flat/threaded), `max_depth` 1-3, `per_page` (0 = all, 10, 20, 50). A placement is a plain `shared_ref` with `children []`.
- Page row (per page, unchanged): every `comments_*` policy column. One record's thread: `allow_new_comments_for_items`. Comments, watchers: their rows. The thread key (page_id, item_type, item_id) is never stored: it comes from the drawn page and the record it shows.

## Which names does the designer get?
- One renderer `includes/fn/widgets_comments.php`, one JS list; `tools/check_bindings.php` checks both ways. Thread tokens `__comments_*` (title, count, label, label_plural, intro, closed_message, show_all_url, show_featured_url), `__comment_author_name`, `__reply_to_name`, `__watcher_count_text`, reused `__login_url`.
- Row tokens `__comment_*`: id, anchor (`c-<id>`, also the row root id), url (`?comment=<id>#c-<id>`), name, staff_name (staff only), message_html (raw), date, datetime (`%%format%%`), rating, stars_html (raw), badge, file_name/url/size, status, seller_reply_html (raw), seller_reply_date, edit_url (staff), reply_url (`?reply_to=<id>#software_add_comment`), parent_id/name/url, quote (escaped excerpt), depth, reply_count.
- Thread flags: comments_open, comments_closed, can_comment, must_sign_in, is_signed_in, is_signed_out, has_comments, no_comments, featured_view, has_featured, has_more_pages, is_replying, is_staff, name_is_typed/chosen/fixed, rating_on, files_on, watching_on, is_watching, not_watching, can_manage_watchers, is_email. Row flags: comment_is_root/reply/featured/pending/own_pending/scheduled, comment_has_quote/replies/rating/file/badge/seller_reply, comment_is_verified, comment_can_edit.
- Forms (action bindings): `comment_form` -> add_comment.php (server writes action, enctype, `id="software_add_comment"`, token, hidden send_to/page_id/item_id/item_type/parent_id), `comment_watch_form`, `comment_allow_new_form` (staff). Values: comment_message, comment_name, comment_name_type, comment_file, comment_watcher. Server sections: comment_captcha, comment_rating_input, comment_publish, comment_watchers, comment_pagination. Messages `add_comment` (re-stamped inside the two small forms).
- Thread order is the pure `pg_comments_thread_order()`: flat = by time, a reply quotes its parent; threaded = depth-first, capped at `max_depth`; a reply to a deleted comment draws as a root.

## Which security rules hold in every slice?
- The thread key is worked out again on post (`pg_comment_thread_verify()`); on a form_item_view page only `submitted_form` is accepted.
- The record visibility gate (access setting and record filters) stays: hidden record = no output, post refused.
- `parent_id` must be in the same thread and visible to the writer; else refused with the typed text kept, never set to 0.
- Escape once (`pg_sw_escape_token_values()`); raw keys only message_html, stars_html, seller_reply_html; visitor text writes `^` as `&#94;`; raw values through `pg_defuse_php_tags()`.
- Staff-only values are empty and their flags false for everyone else; watcher addresses only for role < 3.
- Each handler keeps its CSRF token, CAPTCHA, allow-new, sign-in, upload and redirect checks.
- Search index and e-mail renders are anonymous, published-only, without forms.
