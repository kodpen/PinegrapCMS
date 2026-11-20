---
name: workspace-meeting-link-screen
description: The Workspace screen My meeting link (PR #10147): who sees it, link with Copy and QR, one short address, the meeting address, services with a deposit one marked
module: booking_link_screen
---

## Who sees the screen?
- A user with a switched-on `booking_staff` row below role 3 (`pg_booking_link_screen_member()`); anybody else gets a 404 and has no entry. Its entry `meeting_link` sits behind More after Calendar subscription (`ws_nav_groups()` flag `meeting_link`, `ws_side_screen()`).
- Who is staff, their services and hours stay the administrator's in Settings, Bookings; the screen changes none of it.

## What does it show?
- The link (`pg_booking_link_url()`) shown whole and wrapping (never cut off in a field), with Copy beside it (under it on a phone), its QR code (`pg_qr_svg()`, white box so it reads in the dark theme), Open the page and Download the QR code (an SVG data URI). The QR always carries the link itself: a short address can be renamed or removed, and a printed code must keep working (architect, agent channel message 7881).
- A member with no services: a warning and no address (the link would answer 404). A site with no key: a warning that it cannot make links.
- Services on your link: the member's services from `pg_booking_widget_data()`, each "On your link" or "Not on the link: it takes a deposit" (`pg_booking_link_screen_services()`, pure).
- Copy is the calendar subscription screen's inline pattern (clipboard API, `execCommand` fallback, a polite status line); `assets/js/workspace.js` is not touched.

## How is the short address kept?
- One `short_links` row with `destination_type = 'url'` and `url` the site path `PATH . SOFTWARE_DIRECTORY . '/book_with.php?c=<code>'` (`pg_booking_link_short_target()`): a path, so a new host name keeps it. `pg_booking_link_short_link()` finds it (live, not one-time, oldest first).
- The first version (#10147) saved `PATH . 'book_with.php?c=<code>'`, a root path the router answers with a 404. The screen also finds a row of that shape and writes the right path into it when the member opens it (bug hunt PR).
- The site address stands on its own line above the name field at every width: a real host is long and would squeeze the field (architect, agent channel message 7881).
- Saved through the short links' own checks: `pg_short_link_form_validate_name()` (name rule, pasted address, free name, binned name) and `pg_short_link_redirect_columns()` (loops). Save renames the row; Remove moves it to the Recycle Bin (`pg_short_link_to_bin()`) after a question. The link itself stays.

## Where is the meeting address saved?
- The Meeting address card posts to `pg_booking_link_meeting_url_save()` (cleaned by `ws_meeting_url_clean()`, empty is none); a wrong address comes back as the field's error.

## Which API route reads it?
- `GET /workspace/meeting-link` (`workspace.meeting_link.get`, `includes/workspace/api_meeting_link.php`, #10344): `bookable`, `link_url` and `short_url` (null for none), `meeting_url` ('' for none), `services` `{id, name, length_minutes, price, offered, reason}` (price in minor units, reason '' or `deposit`), for a team device client.
- `workspace:read`, `ws_api_person_only()` (a key 403); no booking staff 404 `not_found`, as the screen. Read only: it does not put an old short link's path right, the screen does. Pure answer: `ws_api_meeting_link_present()`.

Test: `tests/booking_link_screen_test.php`, `tests/workspace_api_meeting_link_test.php`. Link and code: `docs/memory/commerce/booking-link-code.md`.
