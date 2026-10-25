---
name: comments-widget
description: The comments system widget (regionType comments): whose thread it shows, options, the recent mode, tokens, flags, escaping, default layout, checks
---

## What is it and where does it live?
- Kind `comments` (`includes/fn/widgets_comments.php`, `SW_TYPES`, `pg_sw_type_labels()`): a page's thread and add-comment form ([[comments-widget-form]]) in the design's layout, shared by every page placing it; comments and `comments_*` settings stay the page's ([[comment-thread-rules]]).
- `mode 'recent'` (kind `comments_recent`): other pages' newest comments, [[comments-widget-recent]].
- Options (`system_region_config`, `pg_comments_widget_options()`, JS `_swTypeDefaults()`): `order` oldest|newest, `featured` first (default, featured top-level pinned) | filter (the block's, `?comments=all`) | none, `layout` flat|threaded, `max_depth` 1-3 (replies: [[comments-widget-replies]]), `per_page` 0|10|20|50 ([[comments-widget-pages]]). Panel: [[comments-widget-panel]].

## Which thread does a page show?
- The drawn page's (`pg_sw_render_context()['page_id']`). A top-level form_item_view widget (`pg_sw_record_comment_context()`): that record's thread, posted as `submitted_form`. Else the page's own: every comment on the page (as the classic block), posted as `''` / 0.
- Left to the classic block, unclaimed: `form item view` pages, catalog, catalog detail and calendar event view pages without a record widget ([[comments-widget-hand-off]]).
- Prints nothing (page claimed): an e-mail, comments off, a record not shown (hidden or none), a form_item_view below the top level (`pg_comments_page_nested_record()`: one query a level, 8 levels, 500 rows), a second comments widget.
- The search index reads a visitor's view: published only, no form, not staff.

## Which tokens and flags does it give?
- Thread: `__comments_title|count|label|label_plural|intro|closed_message|show_all_url|show_featured_url`, `__login_url`, `__comment_author_name`, `__reply_to_name`, `__watcher_count_text`. Row: `__comment_id|anchor|name|staff_name|message_html|excerpt|date|datetime|rating|stars_html|badge|status|file_name|file_url|file_size|seller_reply_html|seller_reply_date|edit_url|url|reply_url|parent_id|parent_name|parent_url|quote|depth|reply_count`.
- Flags: 22 thread (`pg_comments_widget_thread_flags()`), 14 row (`pg_comments_widget_row_flags()`); the loop gets row + thread flags. Watch box, watcher list, staff form: [[comments-widget-watch-staff]].
- Still to come: `is_email` (e-mails).

## How is it escaped?
- Once: `pg_comments_widget_escape()` = `pg_sw_escape_token_values()` (raw: `__comment_message_html`, `__comment_stars_html`, `__comment_seller_reply_html`, defused) then every `^` as `&#94;`; filled in one `strtr()` pass. `__comment_datetime` is formatted (`pg_sw_apply_tokens()`) before visitor text goes in.
- Row root gets `id="c-<id>"` after `pg_sw_uniquify_row_ids()`, and `pg_comments_row_anchor_links()` takes the row suffix off `href="#c-<n>…"`, so a link to `#^^__comment_anchor^^` reaches it. Output starts with `<a id="software_comments">`.

## Where is the default layout?
- `pg_comments_default_tree()`, `_buildStarterTree('comments')` and the server fallback (its loop children, wrapped in `.pg-comments-list`, put first when a stored tree has no loop_area, in the slot of an empty one). Change all three; `test_comments_widget_default_tree_matches_starter` compares bindings, classes and tags in order.
- A designed list the visitor does not get (below a false flag, in an undrawn form) takes its rows with it.

## How was it checked?
- `tests/comments_widget_test.php`, `designer_messages_test`, `check_bindings`.
- Sandbox, MariaDB, PHP 8.3 (2026-10-24): page and record threads; anonymous, member, admin; refused post, closed, sign-in, featured modes, own pending, scheduled, CAPTCHA, files, stars, publish picker (Chromium), two widgets, widget + block; the JS starter draws as the PHP tree; classic and block pages as on the base. Later fixes (hidden list, anchor links, classic pages, form fallbacks): unit tests only.
