---
name: comment-admin-notification
description: The administrator e-mail add_comment.php sends about a new comment: queued, written in the staff language, attachment line only when a file came
---

## When is it sent?
- `add_comment.php` mails the page's "Administrator E-mail" address (and the other addresses it adds to `$administrator_email_addresses`) about every new comment, published or waiting for moderation.
- The body is built inside `pg_staff_lang()` (#3901), so moderators read the software language, not the page's.

## Does the writer wait for it?
- No. Since #4336 (#4362) the call passes `'queue' => true`, like the submitter and watcher e-mails in `includes/fn/mail.php`. `email()` hands it to `mail_outbox` when the general job is alive and sends it at once otherwise, so a site without cron still gets it.
- Before, it was sent within the request: a slow mail server added 1-2 seconds to every post.

## Why are `$file_name` and `$file_size` set before the attachment branch?
- The mail closure takes `$file_id, $file_name, $file_size` in its `use` list, and `use` reads the variables even when the attachment line is skipped. `$file_size` was set only when a file came, so every post without one logged "Undefined variable $file_size" (#4337, #4362). They are now set beside `$file_id = 0` (`''` and `0`).

## How is it checked?
- `tests/comment_admin_mail_test.php`: source tests for the queued call and the defaults' place. No running instance.
