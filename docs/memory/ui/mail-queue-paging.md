---
name: mail-queue-paging
description: The mail queue screen's e-mail and SMS lists on the list table driver, the row confirm, the wrapping long-text cells, Retry while the queue is inactive, and the screen's wording
module: mail_queue_list
---

## Where is the mail queue now?
- Since MA2b (#9275) `mail_queue.php` is a door to Communication › Sends (`messages_sends.php?channel=email`, `?tab=sms` to `channel=sms`; `platform/messages-area-sends.md`). Sends keeps this screen's rules below: the row confirm on `no-submit` buttons, the wrapped long texts, Retry sending at once while the queue is inactive, the Permanent badge. The two list sources stay for the driver's definitions.

## Why are the lists paged on the server?
- The screen printed the newest 100 rows of `mail_outbox` / `sms_outbox` while the count cards counted the whole table: with 181 failed messages, 81 could not be found, retried or deleted, and search saw 100 rows (#5950). Failed rows are kept 30 days, so more than 100 is normal after an SMTP outage.
- Since #6380 both are list table driver lists (`mail_queue`, `mail_queue_sms`; `pg_mail_queue_list_source()`, `pg_sms_queue_list_source()`: role <= 2, queue table present): the first batch printed, the next through `list_table_rows`, newest first (`created_at desc`, id last) unless a heading is clicked. Columns, rows and search live in `includes/fn/mail_queue_list.php` (`tests/mail_queue_list_test.php`).
- Search: e-mail recipient, subject, last error, status and type labels; SMS recipient, body, reference, last error, provider id, status and kind labels.

## How do the row buttons still work?
- The list stays inside the screen's POST form, so Retry/Delete submit `mail_action` / `sms_action` as before. The software-wide confirm handler binds at DOM ready only to the buttons then on the page, so later batches would delete unasked: the row buttons carry `no-submit` (the global handler skips them) and `backend.src.js` asks through a delegated `.pg-list-table button.no-submit[data-confirm-content]` handler, for the printed rows and the loaded ones alike.

## Why is the 28rem limit on an inner block?
- `table.chart td` is `nowrap`, and a `td`'s `max-width` does not narrow an auto-layout column: the "Last error" cell grew to 736 px and the table to 2367 px (#5951). Subject, error and SMS body sit in `div.text-break` with `white-space:normal;min-width:12rem;max-width:28rem` (`pg_mail_queue_list_wrap()`), as the firewall log does (`waf-log-paging.md`).

## What does Retry do while the queue is inactive?
- With neither the general job nor `mail_job.php` run in 15 minutes (`pg_mail_job_alive()` false) nothing works the queue, so a requeued row waited for Run now while the notice said "next run" (#5954). Retry now sends that one row at once: `pg_mail_queue_run(1, 20, $id)` (the third argument limits the run to that id). Sent: "The message was sent."; not sent: a warning that it waits until Run now.

## Other fixes on the screen?
- A non-string `mail_action` reads as empty (`is_string()`, like `sms_action`); `mail_action[]=` printed "Array to string conversion" (#5952).
- Wording (#5953): both tabs head the column "Kind" (Tür); the send gate's status reads "Blocked" (Engellendi); the Permanent badge says the queue does not retry it by itself (the Retry button stays); a sent row's delete question drops "and not sent"; this screen's Turkish says "genel görev" and "İleti". Other screens still say "genel iş".
