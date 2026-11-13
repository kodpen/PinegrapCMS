---
name: messages-area-sends
description: Communication › Sends (MA2, #9252): the UNION ALL list of five branches, row ids, filters, buttons, e-mail sources, the measured batch times, why it waits
module: messages_sends
---

## What does the Sends list read?
- One `UNION ALL` derived table (`pg_messages_sends_from()` in `includes/fn/messages_sends.php`) as the list table driver's `from`: e-mail queue (branch 1), SMS queue (2), manual e-mail campaigns (4), SMS campaigns (5), automatic e-mail campaigns as single messages with source "Automatic campaign" (6). 3 is kept for the WhatsApp queue, 7 is the last free number.
- Row id = id * 8 + branch (`pg_messages_sends_sid()`, `pg_messages_sends_sid_parts()`).
- The period (24 hours, 7 default, 30, 90 days) sits in every branch's WHERE on its time: `created_at` (queues), `created_timestamp` (e-mail campaigns), `start_at` or `created_at` for a draft (SMS campaigns). Channel drops branches; state and source are each branch's own condition (the queues on their stored status).
- Role 3 with the Campaign Manager right: no queue branch, campaign branches kept to `created_user_id` / `created_by`.

## Which buttons does a row get?
- E-mail Retry while queued or failed (`pg_mail_queue_retry()`: sends at once while no job works the queue); SMS Retry while queued, failed or outcome unknown and not expired, asked first when unknown; Delete on every row (a campaign through its own delete). Campaign recipients have no Retry. A campaign row folds open to its recipients (`?recipients=<sid>`, at most 200).

## Where does an e-mail's source come from?
- A queueing caller puts `source` in the `email()` properties (`order:<id>`, `form:<id>`, `account`, `comment`, `flow:<id>`); `pg_mail_source_kind()` copies its kind into `mail_type`, else `type` as before. The SMTP connection still follows `type`. Order, form, password reset and comment mails carry it.
- The flow E-mail box queues with `flow:<flow id>` (`ws_scheduled_mail()`'s `$source`): the box reads sent once the mail is queued, as any queued `email()` call. No outcome hook yet.

## Why is the list not shown yet?
- Measured on MariaDB 10.11 with 500,000 rows per queue in 7 days (#9252): first batch (count + 100 rows) 5.5 s, next batch 2.8 s; 24 hours 1.3 s; the union's COUNT alone 3.2 s. With the keyset bound and `LIMIT 101` inside each branch the rows take 12 ms.
- Over 300 ms, so by the architect's rule (`plans/messages-area-decided.md`) the tab opens only after the driver change: `pg_messages_sends_list_open()` answers false, the tab keeps its place rows, `mail_queue.php` stays the queue screen and automatic campaigns stay in the Campaigns list. Step MA2b turns it on.

Test: `tests/messages_sends_test.php`.

Related: docs/memory/ui/messages-area-shell.md, docs/memory/platform/messages-area-census-sends.md
