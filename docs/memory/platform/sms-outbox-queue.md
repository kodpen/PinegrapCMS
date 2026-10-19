---
name: sms-outbox-queue
description: SMS queue (sms_outbox, step 11.0.10): pg_sms_enqueue() options, the general job's pass, retry only when nothing reached the provider, uncertain sends, expiry, dedupe, the SMS tab
---

## How does a message go out?
- A request calls `pg_sms_enqueue($to, $text, $kind, $options)` (`includes/fn/sms.php`): no provider, no network. It refuses what `pg_sms_send()` would (`pg_sms_check()`), a demo site, a schema without `sms_outbox` and SMS not set up, so nothing collects on a site without SMS. Returns `ok, row, duplicate, error`.
- `job.php` runs `pg_sms_queue_run(10, 15)` on every tick after the mail pass: no setting, no `pg_cron_jobs()` entry, one read when the table is empty. The SMS tab's Run now runs `(10, 20)`. The settings' test message stays immediate.

## What are the options?
- `reference` names what the message is about (`order:438:received`): `[A-Za-z0-9:._/-]{1,64}`, anything else is refused (dropping it would lose the guard). UNIQUE, NULL for none: queueing the same reference again returns the first row with `duplicate`, as long as that row is kept.
- `send_after` (not before), `expires_at` (not after, 0 none); an expiry at or before the start is refused.

## When is a message sent again?
- Only when the send result says `retry` (nothing reached the provider), on the mail queue's ladder (`pg_mail_backoff()`: 1, 5, 30 min, 2, 6 h; six attempts), then `failed`. A next attempt that would fall after `expires_at` fails now.
- `uncertain` in the result (timeout, 5xx, unreadable 2xx, `00` without job id) gives status `uncertain`: never sent again by itself. A row left in `sending` over 15 minutes (a run that died) becomes `uncertain` too; the mail queue resends such a row, an SMS twice cannot be taken back.
- A refusal fails at once. A due message while SMS is off or unreadable fails with "SMS is not set up.", so switching off stops sending instead of a burst later. Expired before its send: `failed`, no attempt, not logged; other failed and uncertain rows go to the activity log.

## What does the table hold?
- `status` VARCHAR(16) queued/sending/sent/failed/uncertain (not an ENUM: a delivery state later needs no step), `attempts`, `send_after`, `expires_at`, `claimed_at`, `sent_at`, `last_error`, `kind`, `recipient` (E.164 digits), `body`, `reference`, `provider`, `provider_id` (Netgsm's job id, for a delivery report). Each run sweeps sent rows after 7 days, failed and uncertain ones 30 days after they were queued, as the mail queue does.
- Columns approved by the owner before the step was written (2026-10-19).

## What does the screen show?
- `mail_queue.php?tab=sms`; the tabs show while SMS is set up or rows remain (`pg_sms_queue_shown()`). Tiles queued, sending, sent in 24 h, failed, outcome unknown; the last 100 rows with provider and job id; Retry for queued, failed and uncertain rows not past expiry (uncertain asks first: it may arrive twice); Delete.
- Queue state and the SMS card's warning come from `pg_sms_job_alive()`: the general job alone, finished in the last 15 minutes. The card links the tab.

## How was it verified?
- `tests/sms_test.php` (row options, outcome ladder, uncertain flags). A sandbox instance: step created and run twice, enqueue, duplicate and refusals, runs through a proxy that refused the tunnel (retried), expiry, a stuck row, retry, delete, sweep, the `job.php` pass, the tab and the card. Netgsm itself was not reached.

Related: docs/memory/platform/sms-provider-layer.md, docs/memory/platform/sms-netgsm-driver.md, docs/memory/plans/sms-and-iys.md
