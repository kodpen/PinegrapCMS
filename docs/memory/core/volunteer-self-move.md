---
name: volunteer-self-move
description: A volunteer's own "Pause my volunteering" and "Leave the programme" (VS8b, G3): one path with the coordinator's move, coming sign-ups given up as a cancel, one coordinator e-mail
module: volunteer_signup, volunteer_page_view
---

## Where are the buttons?
- At the end of each programme on `volunteer.php` and in My Account's volunteering section (`pg_volunteer_self_move_html()` in `pg_volunteer_page_html()`): "Pause my volunteering" for an active volunteer, "Leave the programme" for an active or paused one; an applicant gets neither.
- Each asks first (the `<details>` confirm of `_pg_volunteer_page_button()`, buttons "Yes, pause" / "Yes, leave"): the coming shifts are given up and go to the waitlist, the coordinator is told, shift calls stop, the hours stay; Pause adds that only the coordinator makes it active again.

## What does a press do?
- `pg_volunteer_self_act()` actions `pause` and `leave` run `_pg_volunteer_self_move()`: `pg_volunteer_self_move_check()` (pure) picks the state, then the coordinator's own `pg_volunteer_move()` with user id 0 writes it against the state read, so a second press is refused ("Your volunteering is already paused." for Pause).
- `pg_volunteer_move()` writes the one log line for both paths (`… was moved to paused by themselves` when the volunteer did it); the edit screen logs no second line. No volunteer event exists for pause or leave; none was added.
- Then every signed or waiting row of a shift not started (`pg_volunteer_give_up_rows()`, whatever the cut-off) is given up under its shift's lock by `_pg_volunteer_signup_give_up()`, the code Cancel uses: swap asked of it cancelled, a signed place to the waitlist pass, `shift.signed_up` announced after the lock.
- One e-mail to the programme's coordinators (`pg_volunteer_self_move_mail()`, `pg_volunteer_coordinator_emails()`) names every signed place given up, never one a shift.
- Calls stop with the state: `pg_volunteer_call_people()` asks active volunteers only; the "Shift calls" switch is left as it was for a later resume.

## What stays?
- Paused: rows and hours stay; coming back is the coordinator's Resume, as the paused page says.
- Left: as Mark as left (`left_at` stamped); the signed link stops working, so `volunteer.php` shows the answer on a card instead of the page; the profile goes by the left-volunteers rule of `core/volunteer-store.md`.
- Built in step VS8b (PR #11342). Tests: `tests/volunteer_self_move_test.php`.

Related: docs/memory/core/volunteer-signup.md, docs/memory/core/volunteer-programmes.md, docs/memory/core/volunteer-store.md
