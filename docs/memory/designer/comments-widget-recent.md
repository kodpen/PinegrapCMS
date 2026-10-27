---
name: comments-widget-recent
description: The Comments widget's recent mode (kind comments_recent): what a list shows, options, record filters, what it never does, tokens and flags, checks
module: widgets_comments_recent
---

## What is it?
- A mode, not a kind: `regionType 'comments'`, `mode 'recent'`. `pg_sw_widget_kind()` (widgets.php) and twin `_swKindOf()` name it `comments_recent` for the palette, Change type, starters, census, token and flag lists; `_expand_system_widgets()` dispatches on `comments`, whose renderer hands a list to `includes/fn/widgets_comments_recent.php` before reading the drawn page.
- The newest comments of up to five source pages, each linked to its page; every placement of a row shows the same list. Source pages and who sees which comment: [[comments-widget-recent-gates]]; editor: [[comments-widget-recent-editor]]; statements, cost, cache: [[comments-widget-recent-reads]].

## Which options does it read?
- `pg_comments_recent_options()`: `recent_show` published (default) | mine | pending; `recent_page_ids` ints > 0, each once, first five (`tab:x`, 0, signs, fractions dropped); `recent_limit` 5 | 10 | 20 | 50; `recent_order` newest | oldest (mine, pending).
- `recent_filters` (#1679): rows as a form list stores them, held after the view's own filters to the list's one record source (`pg_sw_list_record_filter_clause()`; an address value such as `?board=` from the page that shows the list, left out when absent). A filtered list over a page's own thread or more than one page draws nothing (decision `filters`, [[comments-widget-recent-gates]]). A `filters` key carried from another kind is never read, so none applies unseen. The thread's options stay for the way back.

## What does it never do?
- Claim a thread, read a liveform, print a form, token, CAPTCHA, `#software_comments` or `id="c-<id>"`; Messages node `comments_recent`. Nothing in an e-mail or for the search index; a background render (Preview, SEO) is anonymous.
- Draw in a comment e-mail either: the thread renderer hands a list over before its e-mail branch. Reached first, that branch drew the named comment in the list's layout and claimed the e-mail page, so the page's thread widget drew nothing (`test_comments_recent_nothing_in_a_comment_email`). `is_email` is among the thread flags a list switches off.

## Which tokens and flags?
- `__comments_count` (drawn); the thread's row tokens minus `pg_comments_mode_off_bindings('recent')`, plus `__comment_thread_title|thread_url|label`; `__comment_excerpt` in both modes. `__comment_url` is the comment on its page (`pg_comment_view_query()` + `#c-<id>`).
- `pg_comments_recent_flags()`: has_comments, no_comments, has_more_pages, is_signed_in, is_signed_out, is_staff; rows add `comment_on_record|on_page`. Other thread flags are absent (fail closed). `pg_comments_drop_mode_bindings()` drops nodes bound to the other mode's tokens, both ways.

## How was it checked?
- `tests/comments_recent_test.php` (also: every thread flag a list lacks is off), `comments_widget_test`, `designer_messages_test`, `check_bindings`.
- Sandbox, MariaDB 10.11, PHP 8.3 (2026-10-25): 5 viewers (anonymous, 2 members, a role-3 editor of one folder, admin) x 2 host pages, 24 marked comments: nothing hidden leaked, each source as expected; two anonymous answers identical; member B after an anonymous post in the same browser saw none; a thread beside a list kept its form and refused-post error; Preview (as admin) and search index drew the anonymous view.
