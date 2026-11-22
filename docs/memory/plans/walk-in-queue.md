---
name: walk-in-queue
description: Plan (owner 2026-11-20, poll 201): a walk-in queue: places and desks, a number by QR, site or kiosk, the person's page, SMS and WhatsApp, Call next, priority, bookings, the estimate; WQ1a to WQ9
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 201, message 10980): "Evet, plana girsin": taking a number, remote join, the person's page, notices, desks and Call next, priority and bookings, the measured estimate, the lobby screen, report, events, API.
- Recorded in `workflow/decisions-made-product-93.md`.

## What is there today?
- Bookings (`commerce/bookings-model.md`): services, staff with hours, bookings; no place column and no arrived state. Staff set `done` or `no_show` (`pg_booking_agenda_status_write()`); `pg_booking_status_event()` announces only confirmed and cancelled. Gate: `pg_booking_agenda_scope()`.
- QR: `pg_qr_svg()`, SVG only. The course certificate is a printable HTML page (`certificate.php`): the poster's way.
- `pg_sms_enqueue($to, $text, $kind)` with `pg_sms_kinds()` (only `marketing` is commercial); `pg_whatsapp_enqueue()` takes a free `kind`, its consent read from the template's category.
- A public page polls in `chat_site.src.js` (stops while the tab is hidden) and `workspace_guest.php`; no server-sent events. A panel screen with no menu: `output_header()`'s `hide_menu`.
- Scanning: `event_check_in.php` (BarcodeDetector, the code's hash, one conditional UPDATE in `pg_check_in_run()`).
- Signed links: the per-feature HMAC of `pg_booking_manage_token()`, its hash stored.

## What binds it?
- "Call next" takes its row with one conditional UPDATE (`WHERE state = 'waiting'`): two desks never call the same person.
- The person's page and the lobby screen show the number, never a name. The signed link opens that number only; `queue.php` is in `waf_sensitive_scripts()`; the person's and the lobby's status read have a bucket of their own (`waf/waf-queue-status-bucket.md`).
- Bookings keep their rows and moves: an arrival is a queue row naming the booking.
- A notice goes only for the number's own moves, at most three, to the phone given for the number; kind `queue`. A service message: no marketing consent or İYS, no promotion in it; owner's answer, poll 207 (`workflow/decisions-made-product-94.md`).
- The estimate reads "about" and shows from 20 handled; priority groups are the place's setting, the reason seen at the desk.
- Times unix UTC, days in the site's zone, hours in the bookings' shape (`pg_booking_hours_clean()`).
- Personal fields cleared after the keep period (W14); the five privacy registrations (`pg_privacy_erase_tables()`, `pg_privacy_erase_labels()`, `pg_privacy_export_sections()`, `pg_privacy_inventory()`, `pg_privacy_rules_catalogue()`) and `pg_contact_holders()`.

## What did the architect answer? (2026-11-20, agent channel messages 11145, 11146)
- Tables `waiting_places`, `waiting_desks`, `waiting_tickets` (a name with "queue" reads as a job queue beside `push_queue`); owner's yes, poll 206 (`workflow/decisions-made-schema-46.md`). Services with their letters, hours, cap and notices are JSON on the place; a ticket names the service's key, its desk and a booking id. No column on an existing table.
- Place: Calendars › Queue beside Bookings. Roles 0-2, and a role-3 user only as a place's staff, by a scope function of the plan's own module shaped as `pg_booking_agenda_scope()`.
- The parts: `walk-in-queue-screens.md`.

## What is built?
- Features: `walk-in-queue-features.md` (W1 to W14). Steps: `walk-in-queue-steps.md`. Screens: `walk-in-queue-screens.md`. Claim as `[plan:walk-in-queue] <step> — <topic>`.

## What is left out?
- Paid priority, paper tickets from a printer, numbers read aloud, several sites' queues in one place, AI staffing forecasts: each a later yes.
