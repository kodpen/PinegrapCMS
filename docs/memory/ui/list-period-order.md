---
name: list-period-order
description: List screens swap a date range entered the wrong way round (pg_list_period_ordered()); the Site Log's 7/30-day presets count calendar days
module: list_period
---

## What happens to a range entered the wrong way round?
- `pg_list_period_ordered($range)` (`includes/fn/list_period.php`) swaps the six start_/stop_ parts when the start day is after the stop day; other keys stay.
- Orders (on the session, after the defaults), contacts (`pg_contact_list_prepare()`), commissions (`pg_commission_list_range()`) and the Site Log (after `$date_range` is read) call it where they settle their range, so the selects, heading, arrows and query agree.
- `erp_audit.php` swaps its `from` / `to` after building `$filters`, as the panel audit trail does (`audit_view.php`); ERP Profit swaps too.
- Before, Start and End were two unlinked pickers and an End before the Start queried start <= t <= stop: an empty list without a word under a heading like "30 Ekim 2026 – 1 Ekim 2026".
- The submitted forms list (`includes/fn/submitted_form_list.php`) does not call it yet.

## Why do the Site Log presets use strtotime()?
- "Last 7 Days" / "Last 30 Days" start at `strtotime('-6 days' / '-29 days', $today_timestamp)`. Subtracting 6 or 29 × 86400 seconds from today's midnight landed on 23:00 of the day before in the week after the spring clock change, so the range held 8 / 31 days (Europe/Berlin, 2027-03-30: from 2027-03-23).

Test: `tests/list_period_test.php` (a reversed range swapped, an ordered one kept, the screens calling it, the presets in calendar days across the clock change).
