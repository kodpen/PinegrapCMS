---
name: activitypub-comments
description: Fediverse replies as comments: which inReplyTo addresses find a thread, how a note becomes a comments row, moderation and mails, a Delete of the note, a site reply sent back as a Note.
module: activitypub_comments
---

## Which replies become comments?
- The inbox hands a Create(Note) whose `inReplyTo` is under the site's base and whose `attributedTo` is the sender to `pg_ap_reply_receive()` (`platform/activitypub-inbox.md`). `pg_ap_reply_ref()` (pure) reads `inReplyTo`: `<actor>/p/<id>` a page, `<actor>/r/<id>` a record, `<actor>/c/<id>` a reply the site sent (`<actor>` = `pg_ap_urls()['id']`), or an Article's `url` (base + page name, no slash, query or `@`).
- `pg_ap_reply_thread()`: a page or record must be in `pg_ap_published()` now (a record's thread is its record page, `item_type = 'submitted_form'`); a `c` reply goes under that published site comment (`parent_id` set, once the column is there). Anything else, or a page whose comments are off or closed (`pg_comments_settings()`, `pg_comments_allow_new()`), is not stored; the inbox log says why.
- A reply to another remote comment is ignored (`pg_ap_is_ours()`).

## What does the row hold?
- `remote_actor` = sender, `remote_id` = note id; a stored pair is skipped (replays add nothing). `name` = the sender's name and handle, `Alice (@alice@mastodon.example)` (`pg_ap_reply_name()`, 100 characters): the name from its `ap_followers` row, else its fetched document; the handle from the address's last part (`pg_ap_actor_handle()`).
- `message` = `pg_ap_note_text()`: the note's HTML to plain text (paragraphs and breaks as new lines, the leading mention of the site dropped, tags stripped, entities decoded), cut to `PG_AP_REPLY_MAX_TEXT` (10,000). No text: not stored. No file, no rating, `created_user_id` 0.

## Who decides and who is told?
- `published` follows the page's automatic publish switch. `pg_ap_reply_stored()` does what `add_comment.php` does for a new comment: cancels scheduled comments set to be cancelled (when published), the panel notification, the record's counters (`pg_comment_refresh_record_info()`), the moderators' mail to the page's address (marked "From the Fediverse"; no conditional or form-editor addresses), the watchers' and the record sender's mails when published, `pg_page_cache_mark_dirty()`.
- A held remote comment published later goes the panel's way; no Note.

## How is a note removed?
- A Delete from the note's own server calls `pg_ap_reply_deleted($note, $actor)`: `pg_ap_remove_comments()` (`activitypub_moderation.php`, shared with `pg_ap_forget_comments()`) deletes the rows matching both the note and its sender, their notifications, and rebuilds the counters.

## How does a site reply reach the sender?
- `add_comment.php`: a published comment with a parent, on a site with `ap_settings`, calls `pg_ap_reply_send()`. When the parent has `remote_actor` and the feature is on, a Create(Note) (`pg_ap_local_reply_activity()`: id `<actor>/c/<comment id>`, `inReplyTo` the parent's note, to Public, cc followers and the sender, a Mention tag, the text escaped with `<br>` and `<p>`) is queued with `pg_ap_queue()` for the sender's inbox (its follower row, else its fetched document).

## Where are the tests?
- `tests/activitypub_comments_test.php`: the address mapping, the note text, handle and name, the outgoing Note, the Delete and add_comment wiring. Likes and boosts: `platform/activitypub-reactions.md`. The insert, mails and delivery against a database were not run.
