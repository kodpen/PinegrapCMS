---
name: volunteer-board
description: A volunteer programme's Shifts tab (VS3): the week's board by day, role pills and their three colours, Add shift with Repeat weekly, Copy last week, a shift's drawer with Call volunteers and Remove
module: volunteer_board, volunteer_board_view
---

## Where is it?
- The first tab and the default view of `edit_volunteer_programme.php` (`view=shifts`, `week=` any day of the week, `place=`, `shift=` opens a shift's drawer). Built in #11163, step VS3 of plan `volunteer-shifts`.
- Rules and store: `includes/fn/volunteer_board.php`; markup: `includes/fn/volunteer_board_view.php`; both loaded by the screen only. Pure parts tested in `tests/volunteer_board_test.php`.
- Anyone who sees the programme (roles 0-2, its coordinators) adds, copies, calls and removes shifts.

## How is the board drawn?
- Not a grid: the seven days Monday to Sunday as cards, each with its count and "No shifts." when empty; a shift a row: time, place, note, "Weekly" for a series, "N waiting", one pill a role "Driver 3 of 5".
- Colours (`pg_volunteer_role_tone()`): teal when full, rose when short and the shift starts within the programme's call days (default 3, `pg_volunteer_call_due()`), else amber. A started shift is never rose.
- A week with no shifts shows one card "No shifts this week"; with no roles it points to Set-up and Add shift is off.
- Toolbar: Add shift (fill), Copy last week, the programme's Pause/Open, the week with arrows, the place filter (a segmented control, shown with two places or more), the tabs.

## What does Add shift take?
- Place, day, start and end (an end at or before the start clock is the next day; the same clock is refused), places per role 0-99 (`PG_VOLUNTEER_ROLE_MAX`, at least one), a note, Repeat weekly with Weeks up to 26 (`PG_VOLUNTEER_SERIES_WEEKS`).
- A series is separate shift rows a week apart; each carries the first row's id in `series_id` (no series table). Days move on the calendar in UTC, so a clock change never shifts the wall-clock time.

## What does Copy last week do?
- Copies the week before into the week shown, roles and note kept, `series_id` 0; a shift with the same place, start and end already there is skipped, so a second press adds nothing. Under `GET_LOCK('pg_volunteer_copy_<programme>')`.

## What does a shift's drawer do?
- Lists each role with its pill and the names signed up, waitlist rows and offered places marked; a swap waiting for approval has Approve and Refuse (`core/volunteer-signup.md`).
- Call volunteers: for each short role `pg_volunteer_call_pick()` over the active volunteers (skills, completed courses through `user.user_contact`, documents, free hours, their signed shifts nearby); asks only within the call days. Sends the programme's Call message by each volunteer's channel and adds the ids to the shift's `called`, so nobody is asked twice; only between 09:00 and 20:00 (`core/volunteer-messages.md`). The hint says how many.
- Remove (danger): the confirm says how many are told; deletes the shift and its sign-ups and e-mails the signed and waiting volunteers. A shift with a check-in or approved hours stays (button off).

Related: docs/memory/core/volunteer-programmes.md, docs/memory/core/volunteer-shift-rules.md, docs/memory/plans/volunteer-shifts-screens.md
