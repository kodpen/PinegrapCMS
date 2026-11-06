---
name: auto-backup-run-claim
description: auto_backup.php takes the auto_backup lock in cron_runs before it starts and reads the last backup time under it, so overlapping starts write one backup
---

## How does the automatic backup keep to one run at a time?
- A start the dispatcher did not claim for (crontab line, a manager's request to `auto_backup.php`) takes the job's lock with `pg_cron_claim('auto_backup')` (catalogue lock 14400 s) and frees it with `pg_cron_release()` from a shutdown function. A held lock ends the request quietly. A dispatched run (`PG_CRON_DISPATCH`) already holds the same lock.
- Under the lock it reads `config.last_software_auto_backup` again with `db_value()`, not the `LAST_SOFTWARE_AUTO_BACKUP` constant `init.php` loaded, and starts only when that time is more than a day old (0: never ran).
- Before the 2026.4.8 upgrade (no `locked_until`) there is nothing to claim; the claim answers true.

## Why?
- The time is written only when the archive is done (`pg_backup_run_auto()`), and the once-a-day check read the value loaded when the request began. A backup takes minutes to hours, so every crontab start, dispatched tick or manager request during it found the time a day old and wrote another full backup of the same week beside the first (same name, same remote copy). Same pattern as the MailChimp sync flag (#7237): the marker was read apart from being set.

Fixed in #7243. Test: `tests/auto_backup_claim_test.php` (source checks).
