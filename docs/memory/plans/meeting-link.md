---
name: meeting-link
description: Plan (owner 2026-11-16, agent channel poll 82): a booking staff member's own "book a time with me" page at an address only they hand out, on the booking engine, no table; steps SL1 to SL4
priority: 6
---

## What is asked?
- The owner's words and the conditions of the set: `plans/more-features.md`. In the study (agent channel message 6433): a team member's "book a time with me" page with their working hours and busy times.

## What is there today, and what is reused?
- Bookings: `booking_services`, `booking_staff` (one row per user: their services, hours and exceptions), `bookings`; the slot engine (`pg_booking_slots()`), busy times from bookings and the member's Workspace events (`pg_booking_busy_between()`), the hold and the confirm (`pg_booking_hold()`, `pg_booking_confirm()`), mails, reminders, the calendar file, cancel and move by link (`booking_manage.php`), deposits (`commerce/bookings-model.md`).
- The booking widget cannot start on one staff member: its script skips the staff step only when a service has one member, and reads nothing from the address.
- A confirmed booking already stands on the member's Work Calendar (`pg_booking_calendar_items()`). `ws_profiles` is a member's own row (title, working day, mail signature).
- A meeting's address exists only on a Workspace event (`ws_events.meeting_url`); the QR maker is `pg_qr_svg()`; short links have their own checks.

## What binds it?
- `commerce/bookings-decisions.md` (#6302): no second calendar engine, and a booking makes no Workspace event.
- Owner (2026-11-14, `workflow/decisions-made-workspace-27.md`): a private plan is closed to everybody but the people its owner opens it to. The booking engine leaves private plans out of busy times (`workspace/personal-calendar-census-readers.md`).
- The architect's conditions: `plans/website-second-set-conditions.md` for all eight plans, `plans/website-second-set-conditions-per-plan.md` for this one. They are not repeated here.

## What is built?
- The numbered features: `meeting-link-features.md` (M1 to M14). Steps: `meeting-link-steps.md`. Claim as `[plan:meeting-link] <step> — <topic>`.
- In short: no table. Every member who can be booked has a link whose code is worked out from their staff row, and a page of its own that calls the booking engine with that member fixed. The widget is not changed.

## What did the owner decide? (2026-11-16)
- Private plans as busy times, asked with the member's own switch as the proposal. Owner (agent channel poll 85, message 6514): "hayır diğer ekip üyesinin kişisel planları ekibin planlarından önce gelemez. eğer ekip doluluk alanına işlettirmek isterse herkesin göreceği şekilde bir ekip planına döndürür (sadece kendisine atamış olarak yada başkalarını da dahil ederek)".
- So M6: a private plan never takes a booking time; a member who wants the time kept makes it a team event everybody sees.

## What did the architect decide? (2026-11-16)
- Architect (agent channel message 6553), after the owner asked that what exists be grown instead of a table (poll 84): no table. One column on `ws_profiles` for the member's meeting address; the link's code from the `booking_staff` row and the site's secret. That a link cannot be renewed by itself is accepted.
- Architect (message 6554): a service with a deposit is not offered through a link in the first version (M7).
- Architect (message 7221, SL1 #10031): the column is `ws_profiles.meeting_url VARCHAR(500) NULL` (11.0.232), no index, no backfill; every write goes through `ws_meeting_url_clean()` and an emptied field is NULL; a reader takes NULL and an empty string alike as no address. Its rules: `workflow/decisions-made-schema-26.md`.

## What is left out?
- Deposits through a link; a link switched off or renewed by itself, or one that offers only some of the member's services; a meeting room made per booking; the member's own Google or Outlook calendar as busy; one link for several members. Each is a later yes.

## Whose look?
- The owner's: the page a customer books on (SL2) and "My meeting link" (SL3) are new.
