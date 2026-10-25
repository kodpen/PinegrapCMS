---
name: comments-widget-recent
description: The Comments widget's recent mode (kind comments_recent): what a list shows, options, source pages, who sees which comment, what it never does, tokens and flags, checks
---

## What is it?
- A mode, not a kind: `regionType 'comments'`, `mode 'recent'`. `pg_sw_widget_kind()` (widgets.php) and twin `_swKindOf()` name it `comments_recent` for the palette, Change type, starters, census, token and flag lists; `_expand_system_widgets()` dispatches on `comments`, whose renderer hands a list to `includes/fn/widgets_comments_recent.php` before reading the drawn page.
- The newest comments of up to five source pages, each linked to its page; every placement of a row shows the same list. Editor: [[comments-widget-recent-editor]]; statements, cost, cache: [[comments-widget-recent-reads]].

## Which options does it read?
- `pg_comments_recent_options()`: `recent_show` published (default) | mine | pending; `recent_page_ids` ints > 0, each once, first five (`tab:x`, 0, signs, fractions dropped); `recent_limit` 5 | 10 | 20 | 50; `recent_order` newest | oldest (mine, pending).
- No record filters: a `filters` key carried from another kind is never read; a later filter option needs its own key (`recent_filters`) or it applies unseen. The thread's options stay for the way back.

## Which pages add comments?
- `pg_comments_recent_source_decision()` (pure): a top-level Form Item View with a form adds its records' threads, any other page its own (`''` / 0). Refused silently: missing, binned, archived (unless editable), a folder the visitor may not open (live `check_view_access()` without the registration/guest grant; background: public only), comments off (Preview's unsaved settings count), classic (`pg_comments_widget_classic_thread()`), nested record view on a page source, no form, a view filtered on the viewer (`pg_sw_filters_compare_viewer()`, shared with the search index) unless signed in, pending without edit access, mine signed out.

## Who sees which comment?
- Published: `published = 1`, staff too. Mine: the account's `created_user_id`, any state (also earlier sessions' waiting ones; session ids are never read, a session survives a sign-in). Pending: `published = 0`, scheduled too, on editable pages.
- Record gate in the statement, before the LIMIT: the view's form, `forms.complete = 1`, the access setting (`pg_comments_recent_access_sql()`; submitter_only follows user and form editor only), the view's filters strictly (`pg_sw_list_record_filter_clause()`). Closed: no statement.
- Staff values only for staff of the comment's page. No parent, quote, file, seller reply.

## What does it never do?
- Claim a thread, read a liveform, print a form, token, CAPTCHA, `#software_comments` or `id="c-<id>"`; Messages node `comments_recent`. Nothing in an e-mail or for the search index; a background render (Preview, SEO) is anonymous.

## Which tokens and flags?
- `__comments_count` (drawn); the thread's row tokens minus `pg_comments_mode_off_bindings('recent')`, plus `__comment_thread_title|thread_url|label`; `__comment_excerpt` in both modes. `__comment_url` is the comment on its page (`pg_comment_view_query()` + `#c-<id>`).
- `pg_comments_recent_flags()`: has_comments, no_comments, has_more_pages, is_signed_in, is_signed_out, is_staff; rows add `comment_on_record|on_page`. Other thread flags are absent (fail closed). `pg_comments_drop_mode_bindings()` drops nodes bound to the other mode's tokens, both ways.

## How was it checked?
- `tests/comments_recent_test.php`, `comments_widget_test`, `designer_messages_test`, `check_bindings`.
- Sandbox, MariaDB 10.11, PHP 8.3 (2026-10-25): 5 viewers (anonymous, 2 members, a role-3 editor of one folder, admin) x 2 host pages, 24 marked comments: nothing hidden leaked, each source as expected; two anonymous answers identical; member B after an anonymous post in the same browser saw none; a thread beside a list kept its form and refused-post error; Preview (as admin) and search index drew the anonymous view.
