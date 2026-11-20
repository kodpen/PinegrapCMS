---
name: booking-meeting-link
description: Start file of the meeting link: a staff member's "Book a time with <name>" page book_with.php and Workspace screen My meeting link; decisions, PRs, files, left out
module: booking_links
---

## What is the meeting link?
- Every booking staff member who can be booked has one link, `book_with.php?c=<code>`, that only they hand out. A visitor picks one of the member's services, a day and a time and books with that member fixed. No table: the code is worked out from the `booking_staff` row and the site's key.
- Built by SL1 #10031 (code, rules, `ws_profiles.meeting_url`), SL2 #10073 (the page, the mails and the .ics), SL3 #10147 (the Workspace screen My meeting link). Read only API route: #10344.

## What binds it?
- Owner (2026-11-16): a member's private plan never takes a booking time; a member who wants a time kept makes it a team event everybody sees, which the engine already counts as busy. Reason: a member's personal plans cannot come before the team's (agent channel poll 85, message 6514; `workflow/decisions-made-product-64.md`).
- Architect (2026-11-16): no table; one column on `ws_profiles` for the meeting address, the code from the staff row and the site key; a link cannot be renewed by itself. Reason: the owner asked that what exists be grown instead of a table (agent channel message 6553, poll 84).
- Architect (2026-11-16): a service with a deposit is not offered through a link in the first version; the screen says so beside it (agent channel message 6554).
- No second calendar engine and a booking makes no Workspace event (`bookings-decisions.md`, #6302): free times are only `pg_booking_busy_between()` and `pg_booking_day_times()`; the booking widget is not changed.
- The owner accepted the page's look (`workflow/decisions-made-product-75.md`) and the screen's (`workflow/decisions-made-product-70.md`).

## Which file holds what?
- The code, who can be booked (the one 404), the services offered, free times, the meeting address column: [[booking-link-code]] (`includes/fn/booking_links.php`).
- The page, its checks (token, same origin, rate limit, no-store, noindex), hold and confirm with source `link`, the done card, the meeting address in mails (`^^booking_meeting_url^^`) and the .ics: [[booking-link-page]].
- The Workspace screen My meeting link (Copy, QR, one short address, Meeting address, services with a deposit marked) and `GET /workspace/meeting-link`: `workspace/workspace-meeting-link-screen.md` (`includes/fn/booking_link_screen.php`, `workspace_meeting_link.php`).

## Which feature is built where? (checked 2026-11-19)
- M1 code from the staff row and key, nothing stored: #10031. M2 the address, a short address by the short links' checks, Copy and QR: #10147. M3 the screen only for booking staff, role 3 never staff: #10031, #10147.
- M4 the page with services, length, price, month, day, time without script, the form and Confirm: #10073. M5 free times only from the engine: #10031, #10073. M6 private plans never busy: #10031. M7 deposit services left out and marked: #10031, #10147.
- M8 hold and confirm with source `link`, a taken time drawn again: #10073. M9 the meeting address kept on the profile, cleaned by `ws_meeting_url_clean()`, read when shown in mails and .ics: #10031, #10073, #10147. M10 the booking's own mails, reminders, manage link and Work Calendar line, no Workspace event: #10073.
- M11 token, same origin, rate limit, one 404: #10073. M12 no-store and noindex: #10073. M13 a member out of booking has no link, a lost service leaves it, no renewal: #10031. M14 the widget and settings unchanged: all three.
- Open items: none; every M number is in the code.

## What is left out?
- Deposits through a link; a link switched off or renewed by itself, or one offering only some services; a meeting room made per booking; the member's Google or Outlook calendar as busy; one link for several members. Each needs a new yes from the owner.
