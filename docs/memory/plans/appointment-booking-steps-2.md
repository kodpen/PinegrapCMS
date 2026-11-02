---
name: appointment-booking-steps-2
description: Steps BK6-BK10 of plans/appointment-booking.md: Workspace calendar rows, My bookings and manage page, API, deposits, templates and close-out; claim as [plan:appointment-booking] BK<n>
---

## Which steps are there?
- Sizes, tests and memory rules as in `appointment-booking-steps.md`; BK6, BK7 and BK8 run at once after BK3 and BK4.

- BK6 (S) Workspace calendar. After BK3 and BK5.
  - A confirmed booking writes a `ws_events` row on the staff member's calendar (kind `booking`, linked by id, moved and removed with the booking), so `calendar_sync.php` carries it to their Google or Outlook calendar as it carries tasks; the slot engine's busy ranges read that person's `ws_events` (their synced events included) through one function in `includes/workspace/calendar.php`; the Workspace Today screen lists the day's bookings. A move made in Google is read back as a refused move (the booking owns the time), as `workspace-calendar-read.md` handles refused moves. Memory: `workspace/workspace-bookings.md`.

- BK7 (S) My bookings and the manage page. After BK4 and BK5.
  - The account widgets (`designer/account-widget-forms.md`) gain "My bookings" (upcoming and past, reschedule and cancel within the policy); `booking_manage.php` (root) opens from the mail's signed link for a visitor without an account: the same two actions and the `.ics` download; both call the slot engine again for a reschedule. Memory: `designer/booking-widget.md` grows.

- BK8 (S) API, MCP and webhooks. After BK3.
  - Resource `bookings` in `includes/api/resources/bookings.php` with the schema rows (`api/api-endpoint-rules.md`): list, get, create (staff-made), update status, cancel, plus `GET /bookings/slots?service=&staff=&day=`; scope `bookings:*` with its owner ceiling; `dry_run`; MCP tools follow the routes; the `booking.*` events of BK5 in the webhook catalogue. Memory: `api/api-bookings.md`.

- BK9 (S) Deposits. After BK4 and `designer-form-logic.md` FL5.
  - A service with a price may ask for a deposit at the details step: the widget hands the amount to the form payment step (the fee product named in the service), the booking confirms on `pg_order_complete()`, else stays requested and the hold expires; "paid on arrival" marks nothing. Memory: `commerce/bookings-model.md` grows.

- BK10 (S) Templates and close-out. After BK1-BK9.
  - The Salon, Clinic and Law templates get a booking page on the widget beside their form (new installs only; `designer/design-template-*.md` updated); PR body names `[NEW] Added appointment booking: services, staff hours, free time slots on designed pages, confirmations and reminders`; decisions to `commerce/`, `designer/`, `workspace/`; the three plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
