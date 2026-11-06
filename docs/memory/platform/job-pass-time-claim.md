---
name: job-pass-time-claim
description: Window passes of the general job (booking reminders, store credit expiring notices) claim their config_text pass time with pg_config_text_claim(), so overlapping runs never both send
module: config_text
---

## Which passes keep a pass time instead of a per-row stamp?
- `pg_booking_reminders_run()` (`config_text` `booking_reminders_at`, `includes/fn/booking_messages.php`) and the expiring-notice half of `pg_credit_mails_run()` (`credit_mails_at`, `includes/fn/store_credit_mails.php`). Each sends what fell in (last pass, now] and then the window moves on; no row says "sent".

## How is the window claimed?
- The pass reads the stored time with `pg_config_text()` and writes the new one with `pg_config_text_claim($name, $read, $now)` before sending anything. It writes only while the row still holds the value read: an `INSERT IGNORE` when nothing was stored, else `UPDATE … WHERE name = ? AND value = ?`, and it is the pass's only when one row changed (`pg_config_text_claim_query()` builds the statement, tested in `tests/config_text_claim_test.php`).
- A run that loses the claim sends nothing; the run that won covers the same window. Writing the time already stored changes no row and counts as lost: that window is empty anyway.

## Why?
- The general job is not locked against itself: a host with two cron entries, or a web cron next to the crontab, starts two runs in the same second, and a tick that runs past the next minute overlaps it. With the old unconditional `pg_config_text_save()` both runs read the same last time, both wrote, and both mailed (and texted) every reminder and notice of the window.

## What is not covered here?
- The ended-card pass of `pg_credit_mails_run()` closes each card with its own conditional UPDATE (`pg_credit_expire_card()`), which was already safe.
- Abandoned-order campaigns in `job.php` and the ERP overdue digest (`erp_overdue_check()`) pick their rows without a claim too; they are separate fixes.
