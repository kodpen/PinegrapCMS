---
name: mail-queue-permanent-errors
description: Mail queue gives a message up on its first attempt when the failure is permanent (no recipient, empty body, refused address), and tells the panel bell once per given-up message (action mail_failed)
---

## Which failures are permanent?
- PR #1206 (2026.11.0). Decided by structure, never by reading the (localised) error text. `pg_mail_permanent_problem($properties)` (`includes/fn/mail_queue.php`): `no_recipient` when `to` holds no non-blank string, `empty_body` when the body is empty or only white space (plain or HTML, before `prepare_page_for_email()`). Recipient is checked first.
- Inside `email()` (`includes/fn/mail.php`) a local `$permanent` is true for that check and while `addAddress()`, `addBCC()`, `setFrom()` and `addReplyTo()` run: PHPMailer throws there only for an address it refuses, and the address is in the stored properties, so a retry fails the same way. A connected account's From rewrite in between is pure.
- An internationalised address PHPMailer queues and validates later in `send()` is not marked permanent (retried as before).
- `pg_mail_last_error_permanent()` carries the flag next to `pg_mail_last_error()`, reset by every `email()` call and set last in the `catch`.
- A whitespace-only body was sent before; it now fails with "The message has no content." (PHPMailer refused only a truly empty one, `empty_message`).

## What does the queue do with it?
- `pg_mail_queue_gives_up($attempt, $max, $permanent)`: permanent ends at attempt 1, otherwise the 6-step ladder as before. An unreadable stored row was already failed at once.
- The sender notice: before the last attempt the queue passes `notify_sender => 'permanent'`; `email()` then sends the "Email delivery failed" notice only for a permanent failure. Last attempt: `true`. Stored `false` stays false.
- No schema change. The screen tells a permanent failure by `pg_mail_failed_permanently($status, $attempts)`: failed with fewer attempts than `pg_mail_max_attempts()`; the Retry button resets attempts, so the reading holds. `mail_queue.php` shows a dark "Permanent" badge beside "Failed"; the reason is the Last error column.

## How is the panel told?
- Every given-up message (permanent, sixth attempt, unreadable row) is told once, at the end of `pg_mail_queue_run()`, through `pg_mail_given_up_notices()` → `create_notification()`.
- Row: `action = 'mail_failed'`, type error, `title` = reason (clipped 255), `order_total` = recipient (100), `form_id` = `mail_outbox.id`, user `system`. More than 3 in one run: one row, `title` = count, no `form_id`.
- `pg_notification_visible()`: role < 3 (manager and above, as `mail_queue.php`). `pg_notification_display()` reads the subject from `mail_outbox` while the row is kept (failed rows: 30 days) and links `mail_queue.php`. Bell only: no e-mail, no device banner.

## How does it relate to the ops alert "E-mails are not being sent"?
- Different questions, no double row for one event. `mail_failed` = this message is lost (each once, at give-up). Ops `mail_failures` (`docs/memory/platform/ops-alerts.md`) = delivery is failing in volume: 10+ rows of the last day with attempts > 0 (retrying or failed), at most once a day, also by e-mail and push. A single permanent failure never reaches the ops threshold; an SMTP outage raises the ops alert during the retries and the per-message rows only when messages run out of attempts.

## How was it verified?
- `tests/mail_queue_test.php` (classification, give-up decision, permanent reading, notices and the summary row). No running instance: the queue run, the bell row and the screen badge were not exercised against a database.
