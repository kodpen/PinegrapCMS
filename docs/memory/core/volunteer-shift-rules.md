---
name: volunteer-shift-rules
description: Pure rules of volunteer shifts: who fits a role on a shift, the waitlist pass, the cancel cut-off, the unfilled call's pick, show-up rate, hours sums, document notices, the signed link's token
module: volunteer_shifts
---

## Where are the rules?
- `includes/fn/volunteer_shifts.php`, no database, plain arrays, tested in `tests/volunteer_shifts_test.php` (step VS1a, `volunteer-shifts.md`). The store, pages and jobs of the later steps call it.
- Times are the site's wall-clock `YYYY-MM-DD HH:MM[:SS]` strings (DATETIME columns) compared as written, no time zone; `pg_volunteer_minutes()` turns one into minutes.

## Who fits a role on a shift?
- `pg_volunteer_fit_reasons($volunteer, $role, $shift)` gives codes in this order: `state` (not Active), `skill`, `training` (course ids), `document`, `hours`, `busy`; empty means fits (`pg_volunteer_fits()`).
- A document counts when its `valid_until` is the shift's last day or later; a document ending the day before does not; no readable date never counts. An old expired copy beside a valid one is fine.
- Free hours are the bookings' shape (`pg_booking_hours_clean()`); the shift must sit inside one range of its weekday, past midnight on both days. No free hours given means no limit.
- `busy`: another of the volunteer's shifts overlaps; one ending when the other starts does not.

## What are the cut-off and the waitlist pass?
- `pg_volunteer_can_cancel($start, $now, $hours)`: at least the cut-off before the start (exactly at it still can); default 24 hours (`PG_VOLUNTEER_CUTOFF_HOURS`). 25 hours before: yes; 23: no.
- `pg_volunteer_waitlist_pass($count, $signups)`: the free places go to `waiting` rows by `created`, then id; a row with `fits` false is passed over. With two waiting and one place, only the first moves.

## Whom does the unfilled call pick?
- `pg_volunteer_call_pick()`: only while the role is short and `pg_volunteer_call_due()` (not started, within 3 days, `PG_VOLUNTEER_CALL_DAYS`); a volunteer who fits, has `calls` on, is not `called` for this shift and has no sign-up on it. It goes out only between 09:00 and 20:00 (`core/volunteer-messages.md`).

## Show-up rate, hours and notices?
- `pg_volunteer_show_up_rate()`: whole percent `came` of `came` + `no_show` over the last 10 (`PG_VOLUNTEER_SHOW_UP_SHIFTS`); cancels not counted; null with none.
- `pg_volunteer_signup_minutes()`: check-in to check-out, else the shift's length, else the row's own `minutes` (hours outside a shift). `pg_volunteer_hours_total()` / `pg_volunteer_hours_by()` count approved rows only, period ends included.
- `pg_volunteer_document_notice($valid_until, $today, $sent)`: 30 from 30 days before, 7 from 7 days before, each once; in the last 7 days only the 7; a missed day still sends; 0 after the date.

## Who opens a document's file?
- `pg_volunteer_document_access_verdict($holders, $viewer)`: roles 0-2, a coordinator of the programme, the volunteer signed in (by contact), the volunteer of the session's signed link. Called by `get_file.php` (`core/volunteer-document-file.md`).

## How is the signed link's token made?
- `pg_volunteer_link_token($id, $issued_at, $key)`: HMAC-SHA256 of `volunteer-link:<id>:<issued_at>` under `ENCRYPTION_KEY`, the per-feature pattern of `pg_booking_manage_token()`; no new secret. Only `pg_volunteer_link_token_hash()` (sha256) is stored; a new issue or an emptied hash withdraws the link (`pg_volunteer_link_token_valid()`).

Related: docs/memory/core/volunteer-overview.md, docs/memory/core/volunteer-shifts.md
