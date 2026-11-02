---
name: appointment-booking
description: Plan (owner 2026-11-02): appointments with real time slots: services, staff hours, a booking widget, confirmations and reminders, the agenda, Workspace calendars; steps BK1-BK10
---

## What is wrong now?
- The Salon, Clinic and Law templates take a booking as a custom-form record with a preferred day and a time-of-day pick list; staff answer by mail, and two visitors can ask for the same hour. There is no service length, staff hours, free slot, hold or cancellation window ("live calendar slots not built", `plans/design-template-law.md`).
- The pieces exist apart: the Workspace calendar (`ws_events`, `includes/workspace/calendar.php`) with a person's Google or Outlook calendar written and read back (`workspace/workspace-calendar-write.md`, `-read.md`), holiday feeds, the SMS queue (`platform/sms-outbox-queue.md`), campaign profile mails, the one date picker on sites (`ui/site-datetime-loader.md`), Flows triggers on announced events.

## What is proposed?
- Services (name, length, buffers, price, capacity 1 in this plan), staff (a panel user with weekly hours and day exceptions, per service) and bookings (service, staff, start, end, customer, status requested / confirmed / cancelled / no-show / done, source, a signed manage token). A slot engine as a pure function: the service length and buffers over the staff member's hours, minus holidays, existing bookings and the busy times of the person's Workspace calendar (synced events included), within a lead time and a horizon.
- A `booking` system widget for designed pages: service → staff ("anyone" picks the freest) → a month calendar with available days → the day's times → the visitor's details (prefilled for a signed-in account) → confirmation. A slot is held for ten minutes while the form is filled; two visitors cannot confirm the same slot (one unique key, the second gets "just taken").
- Confirmation, reminders (24 h and 2 h), reschedule and cancellation by mail with an `.ics` attachment and by SMS where set up; a manage link that needs no account; the staff agenda (day, week, list); every confirmed booking also on the staff member's Workspace calendar, so it reaches their Google or Outlook through the existing sync; `booking.*` events for webhooks and Flows; an API resource; "My bookings" in My Account.

## Which decisions bind it?
- Global: times kept in UTC, shown in the site's zone with the zone named; week start and clock follow the site (`ui/list-period-navigator-and-clock.md`); no country-specific field.
- No new calendar engine: busy times come from `ws_events` (filled by the sync) and from bookings; the site's event calendars are not touched.
- Installed templates keep working; switching their booking pages to the widget is the last step and changes no installed site.
- Deposits and fees come from `designer-form-logic.md` (the payment step), not from a second payment path.

## Which questions are open? (architect; 1-2 before BK1, 3 before BK5)
1. Tables `booking_services`, `booking_staff` (user id, service ids, hours JSON, exceptions JSON), `bookings` (unique key on staff and start, a `hold_until` time) in the open version's migration; cancelled rows kept 400 days, erasure covers them.
2. Whether a booking needs an account: recommended no (name, e-mail, phone; an account is linked when signed in; auto-registration stays a template choice).
3. The mail actions `booking_confirmed`, `booking_reminder`, `booking_rescheduled`, `booking_cancelled` as new `email_campaign_profiles.action` values (an ENUM widened by appending, as `commerce/shipment-mail-fields.md` did), and the SMS kinds beside the order kinds.

## Where does it start?
- BK1 alone, then BK2-BK5 together. Read the `workspace/` and `designer/` key rules, `workspace/workspace-calendar-read.md`, `platform/sms-outbox-queue.md`, `ui/site-datetime-loader.md`, `designer/system-widget-change-checklist.md`, `pinegrap-salon/booking.php`. Steps: `appointment-booking-steps.md`, `-steps-2.md`.
