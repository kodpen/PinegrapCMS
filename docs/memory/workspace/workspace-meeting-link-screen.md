---
name: workspace-meeting-link-screen
description: The Workspace screen My meeting link (PR #10147): who sees it, link with Copy and QR, one short address, the meeting address, services with a deposit one marked
module: booking_link_screen
---

## Who sees the screen?
- A user with a switched-on `booking_staff` row below role 3 (`pg_booking_link_screen_member()`); anybody else gets a 404 and has no entry. Its entry `meeting_link` sits behind More after Calendar subscription (`ws_nav_groups()` flag `meeting_link`, `ws_side_screen()`).
- Who is staff, their services and hours stay the administrator's in Settings, Bookings; the screen changes none of it.

## What does it show?
- The link (`pg_booking_link_url()`) in a read-only field with Copy, its QR code (`pg_qr_svg()`, white box so it reads in the dark theme), Open the page and Download the QR code (an SVG data URI). With a short address the QR carries the short one.
- A member with no services: a warning and no address (the link would answer 404). A site with no key: a warning that it cannot make links.
- Services on your link: the member's services from `pg_booking_widget_data()`, each "On your link" or "Not on the link: it takes a deposit" (`pg_booking_link_screen_services()`, pure).
- Copy is the calendar subscription screen's inline pattern (clipboard API, `execCommand` fallback, a polite status line); `assets/js/workspace.js` is not touched.

## How is the short address kept?
- One `short_links` row with `destination_type = 'url'` and `url` the site path `PATH . 'book_with.php?c=<code>'` (`pg_booking_link_short_target()`): a path, so a new host name keeps it. `pg_booking_link_short_link()` finds it (live, not one-time, oldest first).
- Saved through the short links' own checks: `pg_short_link_form_validate_name()` (name rule, pasted address, free name, binned name) and `pg_short_link_redirect_columns()` (loops). Save renames the row; Remove moves it to the Recycle Bin (`pg_short_link_to_bin()`) after a question. The link itself stays.

## Where is the meeting address saved?
- The Meeting address card posts to `pg_booking_link_meeting_url_save()` (cleaned by `ws_meeting_url_clean()`, empty is none); a wrong address comes back as the field's error.

Test: `tests/booking_link_screen_test.php`. Link and code: `docs/memory/commerce/booking-link-code.md`.
