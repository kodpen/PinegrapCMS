---
name: messages-area-census-sources
description: MA0 census (2026-11-13): what the e-mail queue, SMS queue, WhatsApp queue, e-mail campaigns and SMS campaigns store per message, how long, and which sends leave no row in any of them
---

## What does each source hold per message?
- E-mail queue `mail_outbox` (2026.4.8): id, created_at, send_after, status (queued, sending, sent, failed, suppressed: Blocked), attempts, last_error, sent_at, mail_type, recipient, subject, properties (the `email()` call as JSON). mail_type, recipient and subject are copies for the screen; the SMTP connection is chosen from properties' `type` (system or campaign), never from the column.
- SMS queue `sms_outbox` (11.0.10): the same shape, status adds uncertain (Outcome unknown), kind (test, order, shipping, otp, reminder, stock, booking, marketing), recipient digits, body, reference naming the record (`order:438:received`, `smscampaign:<campaign>:<contact>`), provider and its id (`sms-outbox-queue.md`).
- WhatsApp queue `whatsapp_outbox` (WA2, planned): sms_outbox's shape plus delivery (sent, delivered, read) from the webhook, template and contact, as step WA0 of `plans/whatsapp-channel-steps.md` proposes it (#9067).
- E-mail campaigns `email_campaigns` (subject, body, page_id, start_time DATETIME, status ready, paused, cancelled or complete, type manual or automatic, email_campaign_profile_id, purpose, created_user_id) with `email_recipients` (email_address, contact_id, complete, failed, attempts, last_error, next_attempt_at, reference_code). A recipient row has no sent time.
- An automatic campaign is one `email_campaigns` row per profile send with one recipient; a profile that follows another is told through `email_campaign_sent`.
- SMS campaigns `sms_campaigns` (name, body, start_at, status draft, ready, queued or cancelled, queued_count, refused_count, created_by) with `contact_groups_sms_campaigns_xref`; each text is a `sms_outbox` row found by its reference prefix.

## How long is each kept?
- Both queues: sent rows 7 days, failed, blocked and outcome-unknown rows 30 days, then swept (`pg_mail_queue_run()`, `pg_sms_queue_run()`); WhatsApp follows SMS.
- Campaigns and their recipients: for good. An SMS campaign's own figures (queued_count, refused_count) stay after its texts are swept.
- The bounce record (`mail_bounces`, one row per address) says whether an address bounced or complained; `pg_mail_bounce_campaign_counts()` gives a campaign's delivered, blocked, bounced and complained counts in one read (`core/mail-bounces.md`).

## Which sends leave no row in a queue?
- Campaign e-mails: the campaign job and the Send window send from `email_recipients` directly (`core/email-campaign-send.md`).
- Flow e-mails: the E-mail box sends through `ws_scheduled_mail()` without `queue` (`includes/workspace/scheduled.php`); the run's own log keeps the count.
- Any `email()` call with `queue` while no job ran in 15 minutes (`pg_mail_job_alive()` false): it sends at once and writes no row. A site without cron has an empty e-mail queue.
- One-time codes, order texts and campaigns' texts always go through the SMS queue.

## What is known about delivery?
- E-mail: accepted by the SMTP server (sent) or not; afterwards only the bounce record. No delivered, opened or clicked: there is no tracking pixel and no per-recipient link redirect. On-site links carry the visitor tracking code (`t=`), counted in the visitor reports, not per recipient.
- SMS: sent or outcome unknown at the provider; no delivery report is read.
- WhatsApp: sent, delivered and read from Meta's webhook (WA plan).

Related: [[messages-area-census-sends]], [[messages-area-census-screens]]
