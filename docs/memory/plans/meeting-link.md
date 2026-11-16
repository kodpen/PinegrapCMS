---
name: meeting-link
description: Plan (owner 2026-11-16, agent channel poll 82): a booking staff member's own "book a time with me" page at an address only they hand out, on the booking engine that exists; steps SL1 to SL4
priority: 6
---

## What is asked?
- The owner's words and the conditions of the set: `plans/more-features.md`. In the study (agent channel message 6433): a team member's "book a time with me" page with their working hours and busy times.

## What is there today, and what is reused?
- Bookings: `booking_services`, `booking_staff` (the user, their services, hours and exceptions), `bookings`; the slot engine (`pg_booking_slots()`), busy times from bookings and the member's Workspace events (`pg_booking_busy_between()`), the hold and the confirm (`pg_booking_hold()`, `pg_booking_confirm()`), mails, reminders, the calendar file, cancel and move by link (`booking_manage.php`), deposits (`commerce/bookings-model.md`).
- The booking widget cannot start on one staff member: its script skips the staff step only when a service has one member, and reads nothing from the address.
- A confirmed booking already stands on the member's Work Calendar (`pg_booking_calendar_items()`).
- A meeting's address exists only on a Workspace event (`ws_events.meeting_url`); the QR maker is `pg_qr_svg()`; short links have their own checks.

## What binds it?
- `commerce/bookings-decisions.md` (#6302): no second calendar engine, and a booking makes no Workspace event.
- Owner (2026-11-14, `workflow/decisions-made-workspace-27.md`): a private plan is closed to everybody but the people its owner opens it to. The booking engine leaves private plans out of busy times (`workspace/personal-calendar-census-readers.md`).
- The architect's conditions: `plans/website-second-set-conditions.md` for all eight plans, `plans/website-second-set-conditions-per-plan.md` for this one. They are not repeated here.

## What is built?
- The numbered features: `meeting-link-features.md` (M1 to M14). Steps: `meeting-link-steps.md`. Claim as `[plan:meeting-link] <step> — <topic>`.
- In short: a link row per staff member and a page of its own that calls the booking engine with that member fixed. The widget is not changed.

## What did the owner decide? (2026-11-16)
- Private plans as busy times, asked with the member's own switch as the proposal. Owner (agent channel poll 85, message 6514): "hayır diğer ekip üyesinin kişisel planları ekibin planlarından önce gelemez. eğer ekip doluluk alanına işlettirmek isterse herkesin göreceği şekilde bir ekip planına döndürür (sadece kendisine atamış olarak yada başkalarını da dahil ederek)".
- So M6: a private plan never takes a booking time; a member who wants the time kept makes it a team event everybody sees.
- The table and the column: with the set's other tables (`plans/more-features.md`).

## What does the architect decide?
- The table's columns and where a booking's meeting address is kept, in SL1's draft PR.

## What is left out?
- Deposits through a link; the member's own Google or Outlook calendar as busy; one link for several members; a notice or buffer of the link's own. Each is a later yes.

## Whose look?
- The owner's: the page a customer books on (SL2) and "My meeting link" (SL3) are new.
