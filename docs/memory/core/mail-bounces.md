---
name: mail-bounces
description: The bounce record (mail_bounces, one row per address) and the send gate the mail queue and campaigns ask before a send; complaint withdraws e-mail consent; resubscribe or address change clears
module: mail_bounces
---

## What is recorded?
- `mail_bounces` (2026.11.0, step `11.0.146`, decided in #5036): one row per address (`address` VARCHAR(191), unique, trimmed and lower-cased by `pg_mail_bounce_address()`), `kind` hard/soft/complaint/manual, `source` (dsn, a transport key, manual), `soft_count`, `first_at`, `last_at`, `message_id`, `campaign_id`, `suppressed`, `cleared_at`. Nothing recorded, nothing gated (legacy unchanged). A suppressed address is never deleted from `contacts` and keeps its consent rows; SMS is not gated.
- `pg_mail_bounce_record($address, $kind, $source, $ref)` writes it through the pure `pg_mail_bounce_next()`: hard, complaint and manual suppress at once; soft suppresses at `PG_MAIL_BOUNCE_SOFT_LIMIT` (3) within `PG_MAIL_BOUNCE_SOFT_WINDOW` (30 days, counted from `first_at`; older ones restart the count). A soft bounce never lowers a suppressing hard/complaint/manual row, it only moves `last_at`.
- Announces `mail.bounced` (hard, soft) or `mail.complained` (`pg_announce()`, webhook catalogue); manual is not. The first suppression writes an activity line.

## What does a complaint do besides?
- Owner decision (#5036, 2b): it is an unsubscribe. `pg_mail_bounce_withdraw_consent()` sets `opt_in = 0` on every opted-in contact with the address and writes a refused `email`/`marketing` consent-log row per contact, source `complaint` (added to `pg_consent_log_sources()`), ip and user agent empty.

## Who asks the gate?
- `pg_mail_address_blocked($address)`: one indexed read; false before the upgrade or for an unkeyable address.
- The mail queue (`pg_mail_queue_run()`), before the send and never after: `pg_mail_bounce_blocked_among()` reads the message's `to` at once; blocked recipients are dropped (`pg_mail_bounce_split()`), and a message left with none gets `mail_outbox.status = 'suppressed'` (ENUM widened first, same step): no attempt, notice or retry; swept with failed rows after 30 days; labelled "Suppressed". Cc/bcc are not gated.
- Campaigns: `pg_email_campaign_skip_suppressed()` (`includes/fn/email_campaign_send.php`) in Send now and the job's claimed path, whatever the purpose: the recipient is marked `complete = 1, failed = 1, attempts = 0` with a `last_error`; `attempts = 0` tells it from a given-up one (which always has an attempt). `pg_email_campaign_send_summary()` takes the suppressed count apart from the given-up ones; the job logs how many it skipped. The legacy job path (before 2026.4.8, tables locked) has no gate.
- `email()` sent directly (not queued) is not gated.

## What clears a block?
- `pg_mail_bounce_clear($address)`: `suppressed = 0`, `soft_count = 0`, `first_at = 0`, `cleared_at = now`; kind and `last_at` stay as history.

## Which answers lift a block?
- Architect decision #5366 (review of #5241: anyone could type any address into a public form): only a write that proves the address is the person's own.
- `pg_consent_log_answers()` clears, on a granted e-mail answer (`pg_consent_log_resubscribes()`) or a changed address (`previous_email` differs), only when its source is in the pure `pg_consent_log_proving_sources()`: `account` (signed-in, own account) and `panel` (staff, the card's "Allow again" included).
- `pg_fs_confirm()` (`includes/fn/form_subscription.php`) clears after its granted row: the signed double opt-in link proves the mailbox.
- `form`, `checkout`, `signup`, `unsubscribe`, `import`, `api`, `iys`, `banner` still write consent rows but never clear. A complaint's `opt_in = 0` (#5036) is unchanged.

## Which screens show it?
- `mail-bounce-screens.md` (campaign counts, Suppressed view, "Allow again", export, erasure).

## How was it verified?
- `tests/mail_bounces_test.php` (pure parts and clear call sites). No database run.

Related: mail-queue-permanent-errors.md, email-campaign-send.md, consent-log-writers.md
