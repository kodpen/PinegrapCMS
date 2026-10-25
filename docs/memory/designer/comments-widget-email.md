---
name: comments-widget-email
description: The comments widget on a comment e-mail page: the one comment mail.php names, when the fixed markup stays, absolute links and dates in the site's zone, no forms
---

Designing it (E-mail canvas state, the link the card must keep, save warnings): [[comments-widget-email-design]].

## Which e-mails can the widget draw?
- Only the submitter and watcher e-mails (`includes/fn/mail.php`), and only to an e-mail page that places a comments widget (`pg_comments_page_has_widget()`: the save warnings' census, cached per request). They pass `array('comment_id' => id)`, read as `pg_sw_render_context()['comment_id']`; any other page gets the old call, byte for byte.
- `_pg_comments_widget_email()` draws when `pg_comments_email_decision()` allows: published; thread `''` or `submitted_form` (product, group, event threads keep the fixed markup); the e-mail page is the thread page's watcher page, or its submitter page for a record's comment.

## When does the fixed markup stay?
- `get_page_content.php` appends `$extra_system_content` unless `pg_comments_email_keeps_fixed_markup()` finds the e-mail page claimed in this pass. The branch claims only after `pg_comments_email_drew()` finds the row's `id="c-<id>"` and an href to its permalink. A list behind a visitor flag (`is_signed_in`, `comments_open`), no link to the comment, a refused comment: nothing drawn or claimed, the markup and its View or Reply link stay.
- A claim also silences the e-mail page's own classic block and a second widget.

## What does it draw, for whom?
- One comment, flat: depth 0, quoting a published parent of the same thread (`pg_comment_parent_ok()`, no viewer). `pg_comments_email_draw_data()` (pure): flag `is_email`; visitor, form, open/closed, featured and paging flags false; `has_comments`, `rating_on`, `files_on`, `watching_on` from the thread page; title "1 <label>".
- `pg_comments_email_strip()` (twin `_sdCommentsEmailDrops()`) drops forms, fields, buttons, btn buttons, `is_replying` boxes and cancel. No session, form state, token, CAPTCHA, viewer or request address is read: the request is the poster's, a moderator's, the job's or an API app's.

## Which links and dates?
- On `URL_SCHEME . HOSTNAME_SETTING . PATH` and the thread page (`?r=` for a record): `__comment_url` and the parent's are the View or Reply address (`pg_comment_view_query()`); `__comment_reply_url` only when the thread page draws the widget, takes comments and replies (`pg_comments_email_reply_ok()`); the file link absolute.
- Every date in the site's zone, never relative. `__comment_date`, `__comment_seller_reply_date`: `get_absolute_time()` with `'format' => 'plain_text'` (the escape printed its `<time>` tag as words). `__comment_datetime`: `pg_comments_email_fill_datetime()`, the design's `%%format%%` through `date()`, none or `relative` the default shape; the record rules wrote "a moment ago" and, for a signed-in sender with a zone of their own (a moderator, the poster), that zone (tooltip, `T`, `e`).
- Stars as characters (`pg_comments_stars_text_html()`): mail clients drop SVG.

## How was it checked?
- `tests/comments_widget_email_test.php`: decision, links, flags, strip, draw, drew, dates (a Tokyo sender on a Chicago site, in a process of its own), both mail functions folded back equal their former text by sha1, renders naming no comment.
- Sandbox, MariaDB 10.11, PHP 8.3 (2026-10-25), queued bodies of base and new code: identical for a photo gallery, a blog record (submitter, watcher), an event, a designed page whose e-mail page has no widget, a members-only list, a product review, an unpublished comment. Widget mails via add_comment.php (anonymous reply), edit_comment.php (staff publish: no staff name), job.php (scheduled); `?email=true` draws none. API publish and the `__comment_datetime` fix: tests only.
