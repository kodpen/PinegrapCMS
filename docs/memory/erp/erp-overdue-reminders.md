---
name: erp-overdue-reminders
description: ERP overdue receivable reminders (erp_overdue_check): thresholds, one summary per period, delivery channels, scheduling, recipients, Workspace collection follow-up.
---

## What does the overdue reminder do?
- `includes/erp/notify.php`, `erp_overdue_check($force, $from_job)` (migration 4.55).
- One summary per period, only documents crossing the threshold for the FIRST time; an announced document is stamped `erp_invoices.overdue_notified_at` and never listed again (only counted in the "still open" total).
- Threshold `ERP_OVERDUE_NOTIFY_DAYS` (0 = off); the account's `erp_accounts.overdue_notify_days` wins when > 0.

## How are reminders delivered?
- No new delivery channel is written; existing ones are called: panel `create_notification(action 'erp_overdue')`; device push `pg_push_enqueue_notification()` for the same row (same call as the order one in core.php); e-mail `email(type 'system', format 'html')`.
- A new notification type = one branch in `pg_notification_visible()` + one in `pg_notification_display()`; write text nowhere else (service worker and bell read from the same function).
- Only one summary sits in the bell: a previous unread `erp_overdue` row is deleted.
- Panel switch off but device on: the row is still written and marked read for everyone (the banner feeds from the row).
- Recipient list empty → `ECOMMERCE_EMAIL_ADDRESS`, if empty → `EMAIL_ADDRESS`; the two fallbacks are alternatives, never both.

## How is it scheduled?
- Two ways: registry job `erp_overdue_job` (86400) plus an hourly attempt from the dashboard that calls `erp_overdue_check()` (WAF bot list pattern, `config.erp_overdue_notify_checked`). The job script does not wait for the hour.
- The period is computed from "now" (`erp_overdue_notify_slot`): a late check does not skip a period, an early one does not send twice.

## How does an overdue invoice become a Workspace task? (#1860)
- Only with `define('ERP_OVERDUE_FOLLOWUP', true);` in config.php and the Workspace on; the reminder must be on too (it supplies the threshold and the once-stamp). A Settings switch or person picker would need a `config` column (schema), so both are config.php constants, as the quote follow-up (erp-alerts.md).
- `erp_overdue_check()` calls `erp_overdue_followups($candidates['new'])` after the stamps: only documents announced for the first time, so a second notice or a closed task never opens another one.
- Opened as `ERP_OVERDUE_FOLLOWUP_USER` (a user id, 0 = none) or else the invoice's `created_by` (`erp_overdue_followup_owner()`); an order's automatic invoice has no maker and is skipped unless a person is chosen. `pg_load_user_row()`, `ws_viewer()`, members only.
- Channel: the first of `ws_customer_channels('erp_account', account)` the person may post in; none: their own task.
- `erp_overdue_followup_input()` (pure): tag `<#invoice:id>`, title "Collect overdue invoice <number>" (never the account's name), due the day it is announced at 23:59, reminded that day at `WS_TASK_REMIND_DAY_TIME` (at once when passed). `ws_followup_task_open(..., true)` hands back an open one.
- The job's CLI line prints how many were opened. Tests: tests/erp_overdue_followup_test.php. Not run against a database.

## Where is the `config` column rule?
- New config fields for this feature must be TEXT, never VARCHAR: see docs/memory/data/config-table-no-varchar.md.

Related: docs/memory/erp/erp-overdue-second-notice.md
