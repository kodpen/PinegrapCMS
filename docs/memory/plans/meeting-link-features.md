---
name: meeting-link-features
description: Plan meeting-link, features M1 to M14: a staff member's link and its code, the address, the page, where free times come from, private plans left out, the booking, the meeting address, safety
---

## What is a meeting link?
- (M1) Every booking staff member who can be booked has one meeting link. Its code is worked out from the member's staff row and a secret of the site and checked by working it out again: nothing keeps it, and it cannot be guessed.
- (M2) The address is `book_with.php` with the code. A short address of the site can point at it, named by the member and checked by the short links' own rules. A Workspace screen "My meeting link" shows it with Copy and its QR (`pg_qr_svg()`).
- (M3) Only a user who is booking staff sees the screen. Who is staff, their services and hours stay the administrator's in Settings, Bookings; a user of role 3 cannot be staff, as today.

## What does the visitor see?
- (M4) A page "Book a time with <name>": the member's services with their length (and price, when one has it), then month, day and time picked without script, as `booking_manage.php` draws them, then name, e-mail, phone, a note, the privacy line and Confirm.
- (M5) Free times come only from the booking engine that exists: the member's hours and exceptions, the service's length and buffers, the site's lead time, bookings and the member's Workspace events as busy (`pg_booking_busy_between()`). No second calculator.
- (M6) A private plan never takes a booking time (owner, agent channel poll 85): the free times are the engine's own. A member who wants a time kept makes a team event, which the engine already counts as busy.
- (M7) A service with a deposit is not offered through a link in the first version; the screen says so beside it.

## What does a booking do?
- (M8) Confirm writes through `pg_booking_hold()` and `pg_booking_confirm()` with the source `link`. The slot's unique rule decides a race: a time just taken says so and the times are drawn again.
- (M9) The member's "Meeting address" on that screen: none, or one address they pasted, cleaned as a meeting's is (`ws_meeting_url_clean()`) and kept on their profile. It is read when shown: in the confirmation, the reminders and the calendar file of a booking made through the link.
- (M10) The confirmation and reminder mails, cancel and move by the mail's link, and the line on the member's Work Calendar are the ones bookings have today (`pg_booking_calendar_items()`). A booking still makes no Workspace event.

## What keeps it safe?
- (M11) The page takes a POST only with its own form token and from the same origin, under a rate limit per address. A wrong code and a member who cannot be booked get the same 404.
- (M12) The page is never kept in the page cache and asks search engines not to index it.
- (M13) A member taken out of booking has no link; a service taken from the member leaves it. A link cannot be renewed by itself.
- (M14) A site where nobody hands a link out changes in nothing: the booking widget, its steps and the settings stay as they are.
