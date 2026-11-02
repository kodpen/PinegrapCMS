---
name: mail-bounces
description: The bounce record (mail_bounces, one row per address) and the send gate the mail queue and campaigns ask before a send; complaint withdraws e-mail consent; resubscribe or address change clears
module: mail_bounces
---

## What is recorded?
- `mail_bounces` (2026.11.0, step `11.0.146`, decided in #5036): one row per address (`address` VARCHAR(191), unique, trimmed and lower-cased by `pg_mail_bounce_address()`), `kind` hard/soft/complaint/manual, `source` (dsn, a transport key, manual), `soft_count`, `first_at`, `last_at`, `message_id`, `campaign_id`, `suppressed`, `cleared_at`. Addresses in `contacts` and orders are never changed or deleted.
- `pg_mail_bounce_record($address, $kind, $source, $ref)` writes it through the pure `pg_mail_bounce_next()`: hard, complaint and manual suppress at once; soft suppresses at `PG_MAIL_BOUNCE_SOFT_LIMIT` (3) within `PG_MAIL_BOUNCE_SOFT_WINDOW` (30 days, counted from `first_at`; older ones restart the count). A soft bounce never lowers a suppressing hard/complaint/manual row, it only moves `last_at`.
- It announces `mail.bounced` (hard, soft) or `mail.complained` through `pg_announce()` (webhook catalogue in `includes/api/outbound/webhooks.php`); manual is not announced. The first suppression writes an activity line.

## What does a complaint do besides?
- Owner decision (#5036, 2b): it is an unsubscribe. `pg_mail_bounce_withdraw_consent()` sets `opt_in = 0` on every opted-in contact with the address and writes a refused `email`/`marketing` consent-log row per contact, source `complaint` (added to `pg_consent_log_sources()`), ip and user agent empty.

## Who asks the gate?
- `pg_mail_address_blocked($address)`: one indexed read; false while the table is missing (before the upgrade) or for an address it cannot key.
- The mail queue (`pg_mail_queue_run()`), before the send and never after: `pg_mail_bounce_blocked_among()` reads the message's `to` at once; blocked recipients are dropped (`pg_mail_bounce_split()`), and a message left with none gets `mail_outbox.status = 'suppressed'` (ENUM widened in the same step, first), no attempt counted, no bell notice, no retry; swept with failed rows after 30 days. The run's result carries `suppressed`. `mail_queue.php` labels it "Suppressed". Cc/bcc are not gated.
- Campaigns: `pg_email_campaign_skip_suppressed()` (`includes/fn/email_campaign_send.php`) in Send now and the job's claimed path, whatever the purpose: the recipient is marked `complete = 1, failed = 1, attempts = 0` with a `last_error`; `attempts = 0` tells it from a given-up one (which always has an attempt). `pg_email_campaign_send_summary()` takes the suppressed count apart from the given-up ones; the job logs how many it skipped. The legacy job path (no retry columns, before 2026.4.8) has no gate: the table cannot exist there and its tables are locked.
- `email()` sent directly (not queued) is not gated.

## What clears a block?
- `pg_mail_bounce_clear($address)`: `suppressed = 0`, `soft_count = 0`, `first_at = 0`, `cleared_at = now`; kind and `last_at` stay as history. Called from `pg_consent_log_answers()` (`includes/fn/privacy.php`) when a person's own granted e-mail answer is written (`pg_consent_log_resubscribes()`: signup, preferences, checkout, form) and when the answer's `previous_email` differs from the saved address (an address change on the preferences form or activation).

## How was it verified?
- `tests/mail_bounces_test.php` (keying, the state moves, the window, clearing, the recipient split, the campaign summary and marker). No running instance: the queue run, the campaign paths, the consent rows and the migration were not exercised against a database.

Related: docs/memory/core/mail-queue-permanent-errors.md, docs/memory/core/email-campaign-send.md, docs/memory/core/consent-log-writers.md, docs/memory/plans/mail-deliverability.md
