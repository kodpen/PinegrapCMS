---
name: bug-hunt-areas-volunteer-shifts
description: The bug hunt of volunteer shifts (VS1 to VS8, 2026-11-24): sign-up, waitlist, swaps, calls and reminders, organiser screens, erasure and export, the signed page; what it fixed and what read clean
---

## What was the area?
- Volunteer shifts after VS1 to VS8 landed: `volunteer.php`, `volunteer_check_in.php`, `edit_volunteer_programme.php`, `includes/fn/volunteer_*.php`, `widgets_volunteering.php`, the volunteer lines of `privacy.php` and `privacy_export.php`.
- Not touched: the API files (`includes/api/resources/volunteers.php` and the MCP tools) while VS9 (#11334) is open.
- Decided, no fault: `core/volunteer-store.md` (erasure deletes the rows), the decisions-made lines of polls 202 and 208.

## What did it fix? (PR #11338)
- Mark as left kept the volunteer's coming places: the waitlist never moved and their link no longer opened to cancel. Mark as left now calls `pg_volunteer_give_up_coming()`, the helper the volunteer's own Pause or Leave uses, so both free them as cancels.
- "Call volunteers" and the job called people for a role removed in Set-up, whose place nobody can take (`pg_volunteer_shift_call_ids()`).
- Document notices went on for an old document after a newer one of its kind was uploaded (`pg_volunteer_document_notices_run()`).
- The Report counted a place handed over by a swap, and a swap request refused or withdrawn, as a late cancel (`pg_volunteer_report_build()`).
- A paused programme's signed volunteers got no day-before reminder (`pg_volunteer_reminders_run()`).
- Approve of a swap handed the place to a taker who no longer fit (paused, busy, document run out); Refuse could not clear a request whose offered place was erased (`pg_volunteer_swap_decide()`).

## What became an issue?
- #11345: leaving the waitlist past the cut-off still counts as a late cancel; telling it apart needs a decision.

## What read clean?
- Two sign-ups at once on one shift: every action runs under the shift's lock and checks its choice again; the unique key holds a double press. The call's `called` list is written conditionally, the reminder claims `reminded_at` first.
- The signed page: wrong, withdrawn and left links give one 404; a member acts only on their own profiles; every POST checks the form token; the QR poster code and check-in steps.
- Roles: role 3 sees only programmes it coordinates, never Set-up or Open/Pause; check-ins and swaps are checked against the programme.
- Erasure and export of a volunteer's rows: `privacy.php` and `privacy_export.php` lines match `core/volunteer-store.md`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/core/volunteer-signup.md, docs/memory/core/volunteer-messages.md
