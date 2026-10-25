---
name: comments-watcher-mail
description: Who gets the watcher e-mail about a new comment: each watcher's address once, never the comment's own writer, on every path that publishes it
---

## When is the watcher e-mail sent?
- `send_comment_email_to_watchers()` (`includes/fn/mail.php`) mails the thread's watchers (`page_id`, `item_type`, `item_id`) when the page has a watcher e-mail page and the comment is published: `add_comment.php` on a post that publishes at once, `edit_comment.php` when a moderator publishes, `job.php` on a scheduled publish, the API and `submit_order.php`.
- `add_comment.php` adds the writer as a watcher only after it sends, so a first post never reached them; later posts and a moderator's publish did.

## Who gets it?
- `pg_comment_watcher_addresses($watchers, $writer_user_id, $writer_email)` (`includes/fn/comments.php`, pure) chooses the addresses. A watcher connected to a user gets it at the user's e-mail, any other watcher at its own address. Each address gets it once, compared without case; a row without an address is skipped.
- The writer is `comments.created_user_id`, joined to `user` for their e-mail. Their user's watcher row is left out, and so is any row carrying their address. A guest's comment (0) has no user, so every watcher gets it.
- Before #1668 a member watching a thread was mailed about their own comment. The submitter e-mail already left the writer out (`pg_comment_submitter_mail_due()`, [[comments-moderation-counters]]).

## How is it checked?
- `tests/comment_watcher_mail_test.php`: the pure list, and the sender's query and loop.
- `tests/comments_widget_email_test.php` freezes the function's text without the widget branch (sha1 and length); a change on purpose updates both values in the same commit.
- No running instance: unit and source tests only.
