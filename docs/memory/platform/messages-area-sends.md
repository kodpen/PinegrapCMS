---
name: messages-area-sends
description: Communication › Sends (MA2 #9252, MA2b #9275, WA2b #9409): the list of six branches, row ids, filters, buttons, e-mail sources, measured batch times, what the old queue screen left to it
module: messages_sends
---

## What does the Sends list read?
- One `UNION ALL` derived table (`pg_messages_sends_from()` in `includes/fn/messages_sends.php`) as the list table driver's `from`: e-mail queue (branch 1), SMS queue (2), WhatsApp queue (3, WA2b #9409), manual e-mail campaigns (4), SMS campaigns (5), automatic e-mail campaigns as single messages with source "Automatic campaign" (6). 7 is the last free number.
- Row id = id * 8 + branch (`pg_messages_sends_sid()`, `pg_messages_sends_sid_parts()`).
- The period (24 hours, 7 default, 30, 90 days) sits in every branch's WHERE on its time: `created_at` (queues), `created_timestamp` (e-mail campaigns), `start_at` or `created_at` for a draft (SMS campaigns). Channel drops branches; state and source are each branch's own condition (the queues on their stored status).
- A WhatsApp row: "template · language" as the message, the number with "+", the kind it was queued with as source, the queue's status. A sent row shows "Read:" or "Delivered:" with the webhook's time instead of the sent time, and "Not delivered" with Meta's error when delivery failed (`pg_messages_sends_whatsapp_delivery()`, one read per batch). The tiles count it.
- Role 3 with the Campaign Manager right: no queue branch, campaign branches kept to `created_user_id` / `created_by`.

## Which buttons does a row get?
- E-mail Retry while queued or failed (`pg_mail_queue_retry()`: sends at once while no job works the queue); SMS and WhatsApp Retry while queued, failed or outcome unknown and not expired, asked first when unknown (the WhatsApp queue's retry, delete and counts are `pg_messages_sends_whatsapp_*()` in the same module); Delete on every row (a campaign through its own delete). Campaign recipients have no Retry. A campaign row folds open to its recipients (`?recipients=<sid>`, at most 200).

## Where does an e-mail's source come from?
- A queueing caller puts `source` in the `email()` properties (`order:<id>`, `form:<id>`, `account`, `comment`, `flow:<id>`); `pg_mail_source_kind()` copies its kind into `mail_type`, else `type` as before. The SMTP connection still follows `type`. Order, form, password reset and comment mails carry it.
- The flow E-mail box queues with `flow:<flow id>` (`ws_scheduled_mail()`'s `$source`): the box reads sent once the mail is queued, as any queued `email()` call. No outcome hook yet.

## How fast is it, and why?
- MA2 (#9252) read the union whole: 5.5 s per first batch with 500,000 rows per queue in 7 days (MariaDB 10.11). Over the architect's 300 ms (`plans/messages-area-decided.md`), so MA2b (#9275) moved the work into the branches: the source gives `branches` and `branch_id` (`rid`, the row's own id), and the driver bounds, orders, limits and counts inside each one (`ui/list-table-driver.md`).
- Same data after MA2b: first batch 132 ms (count 1,000,000 included), next batch 14 ms; 24 hours 24 ms; any channel, state or source under 250 ms.
- Still slow at that volume: search 3.2 s (a LIKE scans every row; the old queue screen took 1.3 s on 500,000) and a heading sort other than Date or Channel about 2 s (a branch sorts its whole period). Not changed: a question for the architect if a site meets it.
- Since MA2b the tab shows the list, `mail_queue.php` is a door (`?tab=sms` becomes `channel=sms`), and the Campaigns tab lists manual campaigns only.

## What did the old queue screen keep on the way?
- The PHP mail() warning with Open SMTP Settings (a `pg_messages_note()` row since MA12), the Permanent pill, Run now (both queues, 10 messages and 20 seconds each), the e-mail queue's active line and last run. An e-mail Retry names the general job or mail_job.php, a text's the general job only.

Test: `tests/messages_sends_test.php`.

Related: docs/memory/ui/messages-area-shell.md, docs/memory/platform/messages-area-census-sends.md
