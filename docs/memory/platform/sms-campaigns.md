---
name: sms-campaigns
description: SMS campaigns beside the e-mail campaigns (PR #3846, #3570, tables decided #3847): the two tables, the audience, the job's queueing through pg_sms_enqueue(), the screens
module: sms_campaigns
---

## Where are SMS campaigns kept?
- `sms_campaigns` and `contact_groups_sms_campaigns_xref` (11.0.137, decided #3847), not the e-mail tables: every e-mail reader (job, send window, history, API, widget) mails a `ready` row of `email_campaigns`, whose `format` is an ENUM and whose recipients key on `email_address`.
- `sms_campaigns`: `name`, `body`, `start_at` (epoch, 0 for a draft), `status` VARCHAR(16) `draft`/`ready`/`queued`/`cancelled`, `queued_count`, `refused_count`, `queued_at`, `created_at/by`, `updated_at/by`; KEY `idx_due (status, start_at)`. The xref: `contact_group_id`, `sms_campaign_id`, `type` `included`/`excluded`. Both in `get_tables()`.
- No recipient table (option B of #3847, deferred): each message is a `sms_outbox` row, kind `marketing`, reference `smscampaign:<id>:<contact id>`. The lasting record is the two counts; sent/waiting/failed figures come from the outbox by reference while it keeps the rows (7 / 30 days).

## How is a campaign sent?
- `job.php` calls `pg_sms_campaigns_run_due()` (`includes/fn/sms_campaigns.php`) just before `pg_sms_queue_run()`, so one sent now goes out in the same run. At most 5 due campaigns a run; each is claimed by `UPDATE … SET status = 'queued' WHERE status = 'ready'` (one affected row), so two runs never queue it twice.
- Audience (`pg_sms_campaign_audience()`), as the e-mail campaign picks it: contacts of the included groups with `contacts.opt_in = 1`, in a subscription group not opted out of it, with a `mobile_phone`; numbers normalised by `pg_sms_number()`, one message per number (the first contact keeps it), numbers of excluded groups' contacts left out. Picked when queued, not when saved.
- `pg_sms_campaign_queue()` calls only `pg_sms_enqueue($number, $body, 'marketing', reference, expires_at)`: consent, İYS refusal, an empty sender identity and SMS not set up are refused there (`sms-marketing-kind.md`), counted as refused, never sent another way. The identity and label are appended by the layer. `expires_at` is the queueing time + 6 h (`pg_sms_campaign_expiry()`): a promotion is not sent the next morning after a stalled queue.

## What do the screens do?
- `view_sms_campaigns.php` (My SMS Campaigns): all campaigns (role 3: their own), status, send time, result counts, Delete Selected (posts back; queued messages stay in the queue). A warning while SMS is not set up or the identity is empty. Linked from My Campaigns' toolbar only while SMS is set up (`pg_sms_ready()`) and the table exists, so running sites see nothing new.
- `edit_sms_campaign.php` creates and edits: name, text, the e-mail screen's Ignore/Include/Exclude group table, When (keep as draft, send now, at a date and time). Cards from `pg_email_campaign_card()` (`ui/email-campaign-shared-form.md`). `pg_sms_campaign_decide()` (pure) refuses a sent campaign without text or group and a past or unreadable time; a draft may lack both. Queued and cancelled campaigns are read-only; a scheduled one has Cancel Campaign.
- Text limit, labels, group delete: `sms-campaign-limit-and-labels.md`.

## What is not done?
- No Flows action, no API route (later). Not sent through a live provider; the step was not run on a database in its PR (no server in the session).
- `pg_sms_marketing_permission()` scans contacts and orders per number, so a campaign of many thousand numbers queues slowly; the contact id could be passed in.

## Tests?
- `tests/sms_campaign_test.php`: the decision, the text limit against `pg_sms_check()`, the status labels, the group delete pins, the audience pick, due, queueing only `marketing` through the layer with refusals counted, no direct insert or send, the job's order.

Related: docs/memory/platform/sms-campaign-limit-and-labels.md, docs/memory/platform/sms-marketing-kind.md, docs/memory/platform/sms-outbox-queue.md, docs/memory/platform/sms-and-iys-rules.md
