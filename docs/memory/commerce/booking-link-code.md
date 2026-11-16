---
name: booking-link-code
description: A staff member's meeting link (SL1): the code from the staff row and site key, one 404 for a wrong code or a member out of booking, its services and free times, ws_profiles.meeting_url
module: booking_links
---

## How is the link's code made and checked?
- `pg_booking_link_code($member, $key)` = `<booking_staff.id>-<32 hex>`, the hex the first 128 bits of HMAC-sha256 of `booking-link|<id>|<user_id>` under `pg_booking_key()` (ENCRYPTION_KEY). Nothing is stored; `pg_booking_link_code_valid()` works it out again and compares with `hash_equals()`. No key, no link.
- The same row and user give the same code for ever: the link cannot be renewed by itself (architect, agent channel message 6553). A row given to another user gets another code.

## Who can be booked through a link?
- `pg_booking_link_member($code)` reads the row the code's id names (`pg_booking_link_staff_row()`, user joined, contact's name only) and `pg_booking_link_member_pick()` (pure) returns it only when the code is right and `pg_booking_link_bookable()`: switched on, user exists below role 3, at least one service. Every other case is null, so the page gives a wrong code and a member out of booking the same 404.

## Which services does a link offer?
- `pg_booking_link_services($services, $member)` (pure): the bookable services of `pg_booking_widget_data()` the member's `service_ids` hold, without a deposit (`pg_booking_deposit_due()` not null is left out, architect message 6554). A service taken from the member leaves the link at once. `pg_booking_link_service()` picks one by id.

## Where do the free times come from?
- Only the engine: `pg_booking_link_busy()` is `pg_booking_busy_between()` for the one member, `pg_booking_link_times()` is `pg_booking_day_times()` with that member alone. A team event the member is in takes its time; a private plan (`ws_personal_plans`) is never read, so it never takes one (owner, poll 85).

## Where is the meeting address kept?
- `ws_profiles.meeting_url` (step 11.0.232, type the architect's). `pg_booking_link_meeting_url()` reads it, `pg_booking_link_meeting_url_save()` writes it cleaned by `ws_meeting_url_clean()` (empty is NULL). `pg_booking_link_booking_meeting_url()` gives it only for a booking with `source = 'link'` (`PG_BOOKING_LINK_SOURCE`), read and cleaned when shown.

Test: `tests/booking_links_test.php`.

Related: docs/memory/commerce/bookings-model.md, docs/memory/plans/meeting-link-features.md
