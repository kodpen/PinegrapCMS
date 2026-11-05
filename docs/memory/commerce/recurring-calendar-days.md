---
name: recurring-calendar-days
description: Recurring start dates and the recurring job's "yesterday" are counted in calendar days with pg_calendar_days_later(), never time() ± 86400, so a clock change does not shift the date
module: core
---

## How are recurring start dates and "yesterday" computed?
- With `pg_calendar_days_later($days, $now = null)` (`includes/fn/core.php`): `mktime()` with the day number moved, same local time of day. Callers: `recurring_payment_job.php` (`$previous_date`), the recurring start date shown in `get_shopping_cart.php`, `get_order_form.php`, `get_express_order.php`, saved by `shopping_cart.php`, `order_form.php`, the cart widget (`includes/fn/widgets_cart.php`), and the legacy gateway profile start in `submit_order.php` (date expression only; that gateway code stays frozen otherwise).

## Why not time() ± 86400?
- A day across a daylight saving change lasts 23 or 25 hours. Just after midnight following the spring change, `time() - 86400` gave the day before yesterday, so the PayPal Payments Pro profile check read the wrong day; in the hour before midnight a start date `time() + 86400 * N` came out a day off. Tests: `tests/calendar_days_later_test.php`.

## Where else does the same trap live?
- ERP overdue reminders use their own `erp_overdue_calendar_slot()` (`erp/erp-overdue-reminders.md`). New day arithmetic on local dates uses `pg_calendar_days_later()` or `mktime()` with the day moved.
