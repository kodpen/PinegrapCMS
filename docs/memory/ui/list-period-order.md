---
name: list-period-order
description: List date ranges: default a month ago to today on every request (pg_list_period_state(), PR #4463), reversed range swapped; Site Log presets in calendar days
module: list_period
---

## What happens to a range entered the wrong way round?
- `pg_list_period_ordered($range)` (`includes/fn/list_period.php`) swaps the six start_/stop_ parts when the start day is after the stop day; other keys stay.
- Orders, commissions and submitted forms call it through `pg_list_period_state()`; contacts (`pg_contact_list_prepare()`) and the Site Log (after `$date_range` is read) call it directly, so the selects, heading, arrows and query agree.
- `erp_audit.php` swaps its `from` / `to` after building `$filters`, as the panel audit trail does (`audit_view.php`); ERP Profit swaps too.
- Before, Start and End were two unlinked pickers and an End before the Start queried start <= t <= stop: an empty list without a word under a heading like "30 Ekim 2026 – 1 Ekim 2026".

## When is the default range a month ago to today?
- Orders (`view_orders.php`), commissions (`pg_commission_list_range($request)`) and submitted forms (`pg_submitted_form_list_defaults($forms, $request)`) settle their range with `pg_list_period_state($state, $request, $now)`.
- A date part in the request marks the range as picked (`range_picked` in the session); it stays until `?reset=true` clears the screen's session. Without the mark the six parts are a month ago to today on every request. A non-numeric part of a picked range falls back to the default.
- Except the whole default range: all six parts equal to today's default (`pg_list_period_default()`), or to yesterday's for a form drawn before midnight and sent after it (`pg_list_period_same_range()`), is not a pick and ends one. The orders' and submitted forms' Advanced Filters send their date pickers filled with the default alongside every filter; before (#4712) a filter by order number alone pinned the range to that day, and after midnight the day's rows were missing again.
- The rows actions (`includes/panel/lists.php`) pass no request, so they read the range the screen kept.
- Before (#4415, PR #4463) the default was written to the session once and kept: after midnight an open session left the day's orders, commissions and forms out of the list and the totals.

## Why do the Site Log presets use strtotime()?
- "Last 7 Days" / "Last 30 Days" start at `strtotime('-6 days' / '-29 days', $today_timestamp)`. Subtracting 6 or 29 × 86400 seconds from today's midnight landed on 23:00 of the day before in the week after the spring clock change, so the range held 8 / 31 days (Europe/Berlin, 2027-03-30: from 2027-03-23).

Test: `tests/list_period_test.php` (a stored default moved to today, a picked range kept, a reversed range swapped, an ordered one kept, the screens calling it, the presets in calendar days across the clock change).
