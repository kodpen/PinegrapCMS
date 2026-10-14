---
name: erp-overdue-reminders
description: ERP overdue receivable reminders (erp_overdue_check): thresholds, one summary per period, delivery channels, scheduling, recipients.
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

## Where is the `config` column rule?
- New config fields for this feature must be TEXT, never VARCHAR: see docs/memory/data/config-table-no-varchar.md.

Related: docs/memory/erp/erp-overdue-second-notice.md
