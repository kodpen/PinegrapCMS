---
name: ops-alerts
description: Operations alerts (includes/fn/ops_alerts.php): codes, the once-a-day rule, channels, where each source is checked, the notification row as the record, settings columns, cron limits
---

## What are operations alerts?
- Failures that used to show only on System Status or in the logs, told on the panel bell (role < 3), by e-mail and as a device banner. Catalogue `pg_ops_alert_codes()` (label, type, panel url, `direct`); one sender `pg_ops_alert($code, $detail)`. 2026.11.0, step 11.0.20, PR #542.

## Where is an alert stored?
- A `notifications` row, `action = 'ops'`, `order_total` = code, `title` = detail (or the code). No table (owner approval 2026-10-19). `pg_notification_display()` words it; `pg_ops_alert_recent()` feeds the System Status tile "Operations Alerts" (reported, not scored).
- `data/temp/ops_alerts_state.json`: send times, last pass, the certificate reading. A cache; Cleanup may delete it.

## When is an alert sent again?
- Once per code per 24 h (`pg_ops_alert_due()`): the later of the newest bell row and the state file. The send is claimed in the state file under `flock()` first, so two processes send once.

## Which channels and settings?
- Bell always (it is the record). E-mail (`ops_alert_email`) to `ops_alert_recipients` or the site address, queued; a `direct` code (`mail_failures`) is sent at once. Device banner (`ops_alert_push`) needs the Device notifications job.
- Settings › General › Alerts (`pgset-alerts`). Thresholds, 0 = check off: `ops_alert_error_rate` 50/h, `ops_alert_disk_percent` 10 (also under 1 GB), `ops_alert_mail_failures` 10/day, `ops_alert_cert_days` 14. `OPS_ALERT*` constants in `init.php`; `OPS_ALERTS` reads 0 before the upgrade.

## Where is each source checked?
- `pg_ops_alerts_run()` from the general job every tick: checks at most every 10 min, a tick overlapping a pass skips (`data/temp/ops_alerts.lock`); one failing check is logged, the rest run.
- `backup_stale`: `pg_backup_newest()` (shared with the tile) older than 8 days; never backed up is not alerted. `backup_failed`: `auto_backup.php` with `pg_backup_last_error()`, or no pdo_mysql. `backup_remote_failed`: `pg_backup_remote_record()`.
- `integrity`: `check_directory_file_integrity()` run with cwd = software dir (relative paths); not in development mode.
- `error_spike`: last MB of `php_errors.log` (and `.1` when written within the hour), lines dated in the site zone; names the most frequent message.
- `disk_low`: `disk_free_space()` of `data/`. `mail_failures`: `mail_outbox` rows of the last day with attempts > 0, queued or failed. `webhook_failing`: `api_webhooks.status = 'failing'`.
- `cert_expiring`: only over https for a public host name; read once a day (`pg_ops_certificate_end()`, same reading as the Workspace web check), judged every pass; a failed reading says nothing.

## Which scheduled jobs count as stopped?
- The catalogue's `stale_after`, as on the tile; the general job's is 2 h (was 6 h): it records its run before dispatching. When it is stopped it is the only one named, and since it cannot report its own death System Status calls `pg_ops_alerts_check_cron()` too (mail then goes out directly).
- Inline job only while switched on (the mail pass always); dispatched job when selected or while dispatching is off; silent a week past its limit = retired, no longer alerted; never run is not a failure.

## How was it tested?
- `tests/ops_alerts_test.php`; sandbox: upgrade twice, job runs for every source (certificate against a real host), forced backup failure, invalid recipient refused, bell endpoint.
