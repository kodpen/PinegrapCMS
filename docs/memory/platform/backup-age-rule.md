---
name: backup-age-rule
description: When a backup's age is a problem - pg_backup_age_state() (none fail, over a month warn, else pass) and its readers: System Status tile, ops alert, greeting, API, agents
module: backup
---

## What is the rule?
- `pg_backup_age_state($newest, $now)` in `includes/fn/backup.php`: `$newest` from `pg_backup_newest()` (the folder, never `config.last_software_auto_backup`). 0 → `'fail'`; older than `PG_BACKUP_WARN_AGE` (2592000 s, 30 days; exactly 30 days still passes) → `'warn'`; anything newer → `'pass'`.
- The threshold lives only there. A new place that judges backup age calls this function; it never compares a timestamp itself.

## How does the System Status tile score it?
- `Last Backup` check in `includes/fn/system_status.php`: `'fail'` red "No backup has ever been taken of this site.", whole weight (5); `'warn'` yellow "The most recent backup is more than a month old.", half; `'pass'` green "A recent backup exists.", nothing. The value is the relative time ("3 days ago"): information, not a warning.
- `SOFTWARE_AUTO_BACKUP == false` with a non-pass age: grey "Automatic backup is turned off.", half the weight. With a passing age the tile stays green.
- A failed remote copy (`backup_remote_error`) still turns a green tile yellow (half) or adds half to a yellow one, and lists the error as a `detail` row.

## Who reads the state?
- The tile writes `state` into `data/temp/system_status_cache.json` (key `Last Backup`). Readers: `widget_2.php` (Backup button on warn/fail), `health_gauge.php` and `v2/page_status.php` (chips), `api/resources/system.php` (`GET /system/status`, also the MCP tool `system.status`), `dashboard/brief.php` (greeting).
- The greeting picks its sentence in `pg_dashboard_brief_backup_advice()`: nothing on ok/info; `'fail'` says no backup was ever taken (never "old"); `'warn'` with `detail` rows is the remote copy (link to the backup settings), without them "more than a month old".
- The operations alert `backup_stale` (`pg_ops_backup_stale()` → `'warn'` of the rule); never backed up and a switched-off schedule send nothing.

## Where did agents get "3 days since the last backup"?
- From `GET /system/status` (Site API or MCP) and the bell (`GET /notifications`): the tile was yellow after two days and red after a week, the alert fired after eight days. No agent prompt asks about backups; the daily summary (`ws_claude_summary_instruction()`) names orders, tasks, approvals, forms, comments and visitors. Under a month the check now reads `ok` and nothing raises an alert.

## Why?
- Owner decision 2026-11-09 (`workflow/decisions-made-product-39.md`): warn only after a month; the warning was noise. The automatic backup is weekly, so the old two-day threshold was yellow most of every week.
- Tests: `tests/backup_age_test.php`, `tests/ops_alerts_test.php`.
