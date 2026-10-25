---
name: comments-widget-email
description: The comments widget on a comment e-mail page: the one comment mail.php names, when the fixed markup stays, absolute links and dates, no forms, the E-mail canvas state, save warnings
---

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
- Dates absolute in the site's zone, `get_absolute_time()` with `'format' => 'plain_text'` (the escape printed its `<time>` tag as words); stars as characters (`pg_comments_stars_text_html()`), mail clients drop SVG.

## What does the designer show?
- Canvas state E-mail (chip, Canvas shows): facts `email`, count 1; hides what the strip drops, no sample comments; a dropped node picked moves to another state. `is_email` is in Show only when.
- `.sd-wrap.sd-state-off:not(#sd-state-shown)`: an id's weight; with two classes a hidden node in a `d-flex` row (display:contents) still showed.
- Save: a page another page names as its comment e-mail page (`pg_comments_is_email_page()`, asked once a widget is found) gets only the `widgets` warning.

## What should a design do?
- Make a copy for this page for the e-mail page: inline colours with a `var()` fallback, words under `is_email`; mail clients may drop the stylesheet.

## How was it checked?
- `tests/comments_widget_email_test.php` (decision, links, flags, strip, draw, drew, both mail functions folded back equal their former text by sha1, renders naming no comment); designer twins, E-mail state, CSS weight.
- Sandbox, MariaDB 10.11, PHP 8.3 (2026-10-25), queued bodies of base and new code: identical for a photo gallery, a blog record (submitter, watcher), an event, a designed page whose e-mail page has no widget, a members-only list, a product review, an unpublished comment. Widget mails via add_comment.php (anonymous reply), edit_comment.php (staff publish: no staff name), job.php (scheduled); `?email=true` draws none; Chromium E-mail chip. API publish: not run.
