---
name: comments-widget
description: The comments system widget (regionType comments): whose thread it shows, hand-off with the classic block, options, tokens, flags, escaping, default layout, checks
---

## What is it and where does it live?
- Kind `comments` (`includes/fn/widgets_comments.php`, `SW_TYPES`, `pg_sw_type_labels()`): a page's thread and add-comment form ([[comments-widget-form]]) in the design's layout, shared by every page placing it; comments and `comments_*` settings stay the page's ([[comment-thread-rules]]).
- Options (`system_region_config`, `pg_comments_widget_options()`, JS `_swTypeDefaults()`): `order` oldest|newest, `featured` first (default: featured top-level comments pinned) | filter (the classic block's, `?comments=all`) | none, `layout` flat|threaded, `max_depth` 1-3, `per_page` 0|10|20|50. layout, max_depth, per_page: stored, not applied yet; no panel control yet.

## Which thread does a page show?
- The drawn page: `pg_sw_render_context()['page_id']`, set by `get_page_content()`; never `_pg_sw_current_page_id()` (0 on the home page, in e-mails and the search index).
- A top-level form_item_view widget (`pg_sw_record_comment_context()`): that record's thread, posted as `submitted_form`. Else the page's own: read as every comment on the page (null thread, as the classic block), posted as `''` / 0.
- Prints nothing for: an e-mail, no page (the editor's Preview, for now), comments off, a record not shown (hidden or none), page type `form item view`, a form_item_view below the top level (`pg_comments_page_nested_record()`: one query a level, 8 levels, 500 rows).
- The search index reads a visitor's view: published only, no form, not staff.

## How does it hand the page over from the classic block?
- It claims the page first (`pg_comments_widget_claim()`), before any other check. A second comments widget on the page prints nothing.
- `get_page_content()` starts each widget pass with an empty record and restores the previous one after it; the classic block skips a page in its pass's record (`pg_comments_widget_drawn()`): no second list, no second add_comment reader, no System-region append; a `<!--pg-comments-block-->` marker is emptied.

## Which tokens and flags does it give?
- Thread: `__comments_title|count|label|label_plural|intro|closed_message|show_all_url|show_featured_url`, `__login_url`, `__comment_author_name`. Row: `__comment_id|anchor|name|staff_name|message_html|date|datetime|rating|stars_html|badge|status|file_name|file_url|file_size|seller_reply_html|seller_reply_date|edit_url`.
- Flags: 17 thread (`pg_comments_widget_thread_flags()`), 10 row (`pg_comments_widget_row_flags()`); the loop gets row + thread flags.
- Still to come: depth, quote, reply, watch, paging (replies, watchers, pagination); `is_email` (e-mails).

## How is it escaped?
- Once: `pg_comments_widget_escape()` = `pg_sw_escape_token_values()` (raw: `__comment_message_html`, `__comment_stars_html`, `__comment_seller_reply_html`, defused) then every `^` as `&#94;`; filled in one `strtr()` pass. `__comment_datetime` is formatted (`pg_sw_apply_tokens()`) before visitor text goes in.
- Row root gets `id="c-<id>"` after `pg_sw_uniquify_row_ids()`; output starts with `<a id="software_comments">`.

## Where is the default layout?
- `pg_comments_default_tree()`, `_buildStarterTree('comments')` and the server fallback (its loop children, wrapped in `.pg-comments-list`, when a stored tree has no or an empty loop_area). Change all three; `test_comments_widget_default_tree_matches_starter` compares bindings, classes and tags in order.

## How was it checked?
- `tests/comments_widget_test.php` (helpers, twin, draw, wiring), `designer_messages_test`, `check_bindings`.
- Sandbox, MariaDB, PHP 8.3 (2026-10-24): page and record threads; anonymous, member, admin; refused post, closed, sign-in, both featured modes, own pending, scheduled, CAPTCHA, files, stars, publish picker (Chromium), two widgets, widget + block. The JS starter draws the same 12 pages as the PHP tree; classic and block pages: 18 masked responses as on the base.
