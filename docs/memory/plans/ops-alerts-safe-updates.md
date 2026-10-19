---
name: ops-alerts-safe-updates
description: Plan (approved 2026-10-19, not started): alerts for backup, cron, integrity, error-spike and disk failures; automatic backup before a software update with rollback; maintenance mode; health URL
---

## Why?
- No operational failure produces a notification: a failed weekly backup, a stopped general job, a file-integrity mismatch or an error spike are visible only when someone opens System Status or the logs. The software update runs check/download/replace with no backup and no rollback. The `tr.json` key "Maintenance Mode" exists but no code uses it. For a self-hosted product these are the cheapest reliability wins.

## What is decided?
- Alerts: a small helper `pg_ops_alert($code, $detail)` in `includes/fn/system_status.php` that writes a bell notification (type `ops`, admins), an e-mail to the admin address and a web push, de-duplicated per code per 24 h (`ops_alert_state` rows in `config`-like key/value or a small table). Sources: backup job failure or no backup for 8 days, cron health stamp older than 2 h (`cron_runs`), integrity check mismatch, PHP error log growth above N per hour (`data/temp/php_errors.log` rotation counter), disk free below 10 % or 1 GB (`disk_free_space`), mail queue failures above N, webhook subscription marked failing (already logged), certificate expiring within 14 days (reuse the Workspace `web_check` SSL logic).
- Settings › General › Alerts card: recipients, thresholds, channels; System Status shows the last alert per code.
- Safe update: before "replace" the updater calls the existing backup routine into a named zip `pre-update-<version>.zip` (files + DB), shown on the Software Update screen with "Roll back to pre-update backup" which restores files through the Repair path and offers the DB restore step (stepwise restore already exists). Setting to skip the backup on very large sites (default on).
- Maintenance mode: setting + optional end time; visitors get a 503 page built from the `error_page` widget (fallback static), staff and allow-listed IPs pass; `Retry-After` header; the WAF and API `/system/status` report it. Nothing changes for sites that never turn it on.
- Anonymous liveness URL `health.php` returning 200/503 and the cron stamp age, for external uptime monitors (no details, rate-limited by the WAF).

## What remains?
1. Alert helper, de-dup state, backup/cron/integrity sources, settings card (S).
2. Error-spike, disk, mail-queue, certificate sources (S).
3. Pre-update backup + rollback button (S–M).
4. Maintenance mode + `health.php` (S).
5. Memory updates in `docs/memory/platform/` (system status, updates).

## Open questions?
- Alert e-mail through the mail queue or direct send? Direct for queue-failure alerts, queue for the rest.

## Where to start?
- Step 1; read `includes/fn/system_status.php:1375` (checks) and `includes/panel/software.php:335-614` (update steps).
