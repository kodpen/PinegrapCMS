---
name: feedback-send-pass
description: Survey pass of the general job (FD3): hours 09:00-20:00, checks before a send, one reminder, no answer after 30 days, consent of service and commercial surveys, periodic NPS
module: feedback_sends
---

## When does the pass send?
- `pg_feedback_job_run(time())` in `job.php`, before the mail pass: periodic rounds first, then sent rows older than 30 days become status 4 (no answer), then the follow-ups' 48-hour escalation (`feedback-followup.md`).
- Only between 09:00 and 20:00 site time (`pg_feedback_send_time()`, the PHP zone `init.php` sets) and only while `pg_feedback_sender()` finds `pg_feedback_message_send($row, $survey, $reminder)` (it loads `feedback_answer.php`, `platform/feedback-answer.md`): up to 200 due rows (`token_hash` set) of e-mail, SMS, or WhatsApp of a survey whose template can be sent (`pg_feedback_due_channel_sql()`, `feedback-whatsapp.md`; others wait queued), then up to 200 reminders.
- First, a queued row due more than 30 days ago is cancelled: its link can no longer be answered.

## What is checked before a send?
- `pg_feedback_send_due()`: survey switched off or archived; `pg_feedback_record_verdict()` 'cancel' (booking not `done`, ticket `void`, order `cancelled` or its review request made in the last 30 days, conversation not `closed`: a customer's new mail reopens it; record gone); `pg_feedback_fatigue()` (any survey in 30 days, "Don't ask me again", a staff address by `pg_privacy_rule_is_staff()`); `pg_feedback_consent_refusal()`. Any of these: status 3.
- A form survey with a field waits ('wait') until the field holds the chosen value, looked at every 6 hours, at most 90 days after the submission, then cancelled.
- Then claimed (status 1, `sent_at`) by a conditional UPDATE, so overlapping runs never both send; a send that fails sets 3.
- The listed test addresses have no setting yet: the `test` list is empty.

## And the reminder?
- Once, 3 days after the send, not answered, link still valid (`pg_feedback_reminder_due()`); `reminded_at` claimed first.

## Which consent?
- `pg_feedback_consent_refusal()` keeps poll 198 (`workflow/decisions-made-product-91.md`): never e-mail to a bounced or complained address (`pg_mail_address_blocked()`), whatever the kind. A service survey asks no consent.
- Commercial (purpose 1; the periodic NPS always): e-mail needs a contact of the address with `opt_in` 1 and no İYS refusal (`pg_iys_email_refused()`); SMS and WhatsApp `pg_sms_marketing_permission()`.

## How does the periodic NPS run?
- `pg_feedback_periodic_run()`: a survey whose months passed (`pg_feedback_periodic_due()`) claims `last_periodic_at` with a conditional UPDATE and starts a round; `config_text` `feedback_periodic` keeps `{survey id: {since, after}}`, claimed with `pg_config_text_claim()` before each page of 500 contacts, so two runs never queue one page.
- Active in the last M months (30-day months): an order, a form, a booking done (by e-mail) or a course lesson; by e-mail only contacts with `opt_in` 1. Rows: `record_type` `periodic`, `record_id` the survey. A round of a survey switched off is dropped.

Test: `tests/feedback_sends_test.php`.

Related: docs/memory/platform/feedback-sends.md, docs/memory/platform/sms-and-iys-rules.md, docs/memory/platform/job-pass-time-claim.md
