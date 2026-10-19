---
name: ops-alerts
description: Operations alerts (includes/fn/ops_alerts.php): codes, the once-a-day rule, channels, where each source is checked, the notification row as the record, settings columns, cron limits
---

## What are operations alerts?
- Failures that used to show only on System Status or in the logs, told on the panel bell (role < 3), by e-mail and as a device banner: `backup_failed`, `backup_remote_failed`, `backup_stale`, `cron_stalled`, `integrity`. Catalogue: `pg_ops_alert_codes()` (label, notification type, panel url).
- One sender, `pg_ops_alert($code, $detail)`. Added in 2026.11.0 (step 11.0.20).

## Where is an alert stored?
- As a `notifications` row, `action = 'ops'`, `order_total` = code, `title` = detail (or the code when there is none). No table of its own (owner approval 2026-10-19). `pg_notification_display()` builds the sentence from the catalogue; `pg_ops_alert_recent()` lists the last one per code for the System Status tile "Operations Alerts" (reported, not scored).
- `data/temp/ops_alerts_state.json` keeps send times and the last pass. It is a cache: Cleanup may delete it; the bell row still holds the once-a-day rule.

## When is an alert sent again?
- At most once per code per 24 h (`pg_ops_alert_due()`), the later of the newest bell row and the state file. The send is claimed in the state file under `flock()` before anything goes out, so two processes send once.

## Which channels?
- Bell always (it is the record). E-mail when `ops_alert_email`, to `ops_alert_recipients` (comma list, saved normalised) or the site address. Queued like other mail; a code marked `direct` in the catalogue is sent at once. Device banner when `ops_alert_push`; needs the Device notifications job selected.
- Settings › General › Alerts (`pgset-alerts`); config columns `ops_alerts`, `ops_alert_email`, `ops_alert_push` (default 1), `ops_alert_recipients` (TEXT). Constants `OPS_ALERT*` in `init.php` read 0 before the upgrade, so nothing runs on the bridge.

## Where is each source checked?
- The general job calls `pg_ops_alerts_run()` on every tick; it runs the checks at most every 10 min and skips a tick that overlaps a running pass (`data/temp/ops_alerts.lock`).
- `backup_stale`: newest backup (`pg_backup_newest()`, shared with the System Status tile) older than 8 days. Never backed up is not alerted; `SOFTWARE_AUTO_BACKUP false` silences it.
- `backup_failed`: `auto_backup.php` when `pg_backup_run_auto()` fails, with `pg_backup_last_error()`, or when pdo_mysql is missing. `backup_remote_failed`: `pg_backup_remote_record()`.
- `integrity`: `check_directory_file_integrity()` run with the working directory set to the software directory (it reads relative paths); skipped in development mode; its own 1 h cache limits the hashing.
- `cron_stalled`: `pg_ops_cron_stalled()`. The general job cannot report its own death, so System Status calls `pg_ops_alerts_check_cron()` too when its tile finds a stopped job (the mail then goes out directly, the queue being idle).

## Which scheduled jobs count as stopped?
- The catalogue's `stale_after`, as on the tile. The general job's limit is 2 h (`pg_cron_jobs()`, was 6 h): it records its run before dispatching, so a long backup does not hold it back. When it is stopped it is the only job named.
- An inline job counts only while switched on (the mail pass always); a dispatched job when selected, or while dispatching is off. A job silent more than a week past its limit is treated as retired and no longer alerted; never run is not a failure.

## How was it tested?
- `tests/ops_alerts_test.php`; sandbox: two upgrade runs, job runs with a stale backup and job, forced backup failure, settings save with an invalid recipient refused, bell endpoint.
