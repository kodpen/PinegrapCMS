---
name: comments-widget-pages
description: Pages and permalinks of the comments widget: per_page units, ?comments_page and ?comment=, the pagination section and fallback, the URL builders, comment=<id> in the redirect, mails and panel
---

## How is a thread paged?
- `pg_comments_widget_paginate($entries, $per_page, $requested, $comment_id)` (pure): a page holds `per_page` units (a top-level comment with the replies drawn under it; flat, each comment), so a reply is never cut from its comment. Every row is still read and ordered in PHP; only the page is drawn.
- The page: `?comments_page=N` (`pg_comments_widget_page_param()`: 1-6 digits above 0) wins, a page past the end is the last; else the page holding `?comment=<id>`; else 1. The search index is never paged (`$search ? 0 : per_page`).
- A well-formed `?comment=<id>` also reads with view 'all', leaving the featured filter.
- Flag `has_more_pages`.

## Where do the page links go?
- The `comment_pagination` section: `pg_sw_pagination_html('comments_page', page, pages, 'software_comments')`, links ending at the widget's anchor (the optional `$fragment`; without it the output is as before). Links keep the other query values, `comment` included.
- A design without the section the visitor gets: right after the comments; beside a list or table that holds them (`_split_widget_tree($tree, true)`'s markers), never inside it. One page: nothing.
- Canvas twin: the section fades while `per_page` is 0.

## Which addresses does a comment get?
- `pg_comments_widget_page_url($path, $params, $drop, $add, $fragment)`: the current query minus `$drop` and non-scalars, plus `$add`, urlencoded.
- `__comment_url` (share, a parent off this page): drops `comment`, `comments_page`, `comments`, `reply_to`, adds `comment=<id>`, ends at `#c-<id>`. The reply link drops only `reply_to`; cancel drops `reply_to`, ends at `#software_add_comment`.
- `pg_comments_widget_view_urls()` (show all / show featured) drops `comment` and `comments_page` too.

## Which other links name the comment?
- `pg_comment_view_query($query_string, $id)` (includes/fn/comments.php): `...?comments=all&comment=<id>`, the caller adds `#c-<id>`. Used by add_comment.php's administrator mail, mail.php's administrator (scheduled publish; a `submitted_form` item links as 'form item view', with `?r=`), submitter and watcher mails, and the panel's "Go To Comment" links (edit_comment.php, view_comments.php, now `h()`-escaped), where the bell notification leads.
- `pg_comment_return_url($path, $id, $featured)`: add_comment.php's redirect after a post. It rebuilds send_to's query (parse_str, http_build_query: arrays get numbered keys) without `reply_to`, `comments_page`, `comment`, with `comments=all` while featured comments exist, then `comment=<id>`; every page, classic ones included.
- The classic comments block ignores `comment=`.

## May a designer read these parameters?
- No: `comments`, `comment`, `comments_page` and `reply_to` are in `pg_sw_url_param_reserved()` and its editor twin `_SW_URL_PARAM_RESERVED`, so a list filter or a form control's `_cf.url_param` cannot take its value from them (the redirect adds `comment=` on every page). A test checks every `$_GET` name the module reads.

## How was it checked?
- `tests/comments_widget_test.php` (paginate, page param, URL builders, fragment, draw), `tests/comment_links_test.php` (both helpers, wiring of the four files).
- Sandbox, MariaDB, PHP 8.3 (2026-10-24): threaded and flat per_page 10 with 13 units; `?comment=` on page 2 and of a capped reply, `?comments_page=` winning; flat featured filter left by `?comment=`; fallback links after the comments; redirect after posts on a widget and a classic photo gallery page; administrator and watcher mails. Submitter and scheduled-publish mails, panel links: source tests only.
