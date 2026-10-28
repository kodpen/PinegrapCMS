---
name: incoming-email-g2
description: G2 of incoming e-mail (IMAP read): G2a client, G2b box card and Test connection, G2c1 fetch run and its job (done or open); G2c2 inline pass in job.php and System status row (next)
---

## Which steps, and where do they stand?
- G2a: the socket IMAP client `pg_imap_*()` (`workspace/mail-imap-client.md`). Done, PR #2764.
- G2b: the box card's server fields and "Test connection" (`workspace/mail-card.md`). Done, PR #2788.
- G2c1: `ws_mail_fetch()` and `workspace_mail_job.php` in the dispatcher's rotation (`workspace/mail-fetch.md`). PR #2806.
- G2c2 (next, not started): run `ws_mail_fetch_run()` inline in `job.php` on every tick while a box with a server exists (no switch, like the scheduled actions; keep the script for a dedicated cron line, mark the catalogue entry `inline`, and change the Mailboxes card text that says the job must be on); a System status row (`includes/fn/system_status.php`) for a box whose `last_error` is set or whose `ok_at` is over an hour old while it is on. Held back in G2c1 because #2802 (`job.php`) and #2797 (`system_status.php`) were open.

## Which read rules were decided?
- In `workspace/mail-fetch.md` (G2c1).

## Where to start?
- G2c2: `job.php` beside the push pass (`require_once` bootstrap and `mail_fetch.php` only when `ws_mail_ready()` and one box has a host: one indexed read on other sites), `pg_cron_jobs()` `workspace_mail_job` gets `'inline' => true`, a check in `get_system_status_checks()` (`platform/system-status-check-rules.md`).
- When G2c2 is merged, G2 is done: move this file's lasting decisions to `workspace/` and delete it; G3 (reply by e-mail) follows in `incoming-email-plan.md`.
