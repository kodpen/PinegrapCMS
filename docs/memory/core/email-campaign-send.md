---
name: email-campaign-send
description: Send now and the campaign job share includes/fn/email_campaign_send.php: mail properties with purpose, refused deliveries stored as attempts, finish counts only deliveries
module: email_campaign_send
---

## Which code do Send now and the job share?
- `includes/fn/email_campaign_send.php`, loaded with `require_once` by `send_email_campaign.php` and `email_campaign_job.php` (PR #4278, #4223, #4224).
- `pg_email_campaign_mail_properties($campaign, $to, $to_name, $subject, $body)`: the `email()` array, `type` `campaign` and the campaign's `purpose`. `email()` adds `List-Unsubscribe` and `List-Unsubscribe-Post` only for `purpose === 'commercial'`; Send now used to pass no purpose, so a marketing campaign sent by hand had no one-click unsubscribe headers. The caller adds `notify_sender` (true only on the last attempt, in both paths).
- `pg_email_campaign_record_failure($id, $address, $attempts_before, $claim_token, $user)`: the UPDATE of a refused delivery, built from the pure `pg_email_campaign_failure()`: `attempts` + 1, `last_error` (500 chars), then either `next_attempt_at = now + pg_mail_backoff()` or, at `pg_mail_max_attempts()`, `complete = 1, failed = 1` and a "given up" activity line. The job passes its claim token (row written only while claimed, claim released); Send now passes `null`.

## What does Send now do when `email()` refuses?
- Before: the answer was ignored and the recipient marked complete; an SMTP outage showed "sent to all recipients", 100%, and nobody was retried.
- Now (with the retry columns, `pg_email_retry_ready()`): the refusal is recorded as above and the recipient stays incomplete. The batch query skips `next_attempt_at > UNIX_TIMESTAMP()`, so a run does not loop on it; one that waits past its backoff during a long run is tried again, as in the job.
- Without the retry columns (2026.4.8 upgrade not run) the old flow stays: the recipient is marked complete whatever `email()` answered.

## How does the window count and finish?
- "Sent" and the percent count only `complete = 1 AND failed = 0`. `pg_email_campaign_send_summary($total, $delivered, $failed, $waiting)` gives the percent (never rounded up to 100 while one is missing), `all_sent`, `complete`, the heading and the message naming how many could not be delivered.
- The campaign becomes `complete` (and an automatic one triggers its follow-ups) only when no recipient waits for another attempt; given-up recipients let it finish, as in the job.
- The JSON `done` answer carries `failed`, `all_sent`, `heading`, `message`; the script writes them into the finished block and turns it `theme-warning` when not all were sent. The activity line names delivered of total and how many failed.

## Why does saving a campaign with the job off not touch start_time?
- `pg_email_campaign_schedule_card()` draws nothing while `email_campaign_job_enabled()` is false, so `start_time` is not posted (#4229). Create reads `$_POST['start_time'] ?? ''`; Edit writes `start_time` only when the field is posted, keeping a time saved earlier while the job was on.

Related: docs/memory/ui/email-campaign-send-window.md, docs/memory/ui/email-campaign-shared-form.md, docs/memory/core/mail-transport-failure.md
