---
name: bookings-decisions
description: Lasting rules of appointment bookings (plan closed in BK10): UTC times, no account needed, no new calendar engine, deposits via the form payment step, open retention work
module: bookings
---

## Which rules bind every booking change?
- Times are stored in UTC and shown in the site's zone with the zone named; week start and clock follow the site (`ui/list-period-navigator-and-clock.md`); no country-specific field.
- No account is needed to book (name, e-mail, phone); a signed-in account is linked; auto-registration stays a template choice (owner, #5032).
- No new calendar engine: busy times come from `bookings` and from `ws_events` (filled by the calendar sync); the site's event calendars are not touched. A booking has no `ws_events` row: one reader feeds the Workspace calendar, Today and the sync (`workspace/workspace-bookings.md`, #6302).
- Deposits and fees go through the designed forms' payment step, never a second payment path ([[booking-deposits]], [[booking-deposit-payment]]).
- Capacity is 1 per staff member and slot; two visitors cannot confirm the same slot (unique key, "just taken": [[bookings-model]]).
- Installed templates keep working: the Salon, Clinic and Law booking pages reach new installs only (`designer/booking-templates.md`).

## Where is each part?
- Model and slot engine [[bookings-model]]; settings `ui/booking-settings.md`; agenda `ui/booking-agenda.md`; widget `designer/booking-widget.md`, `designer/booking-widget-confirm.md`; mails, SMS, reminders [[booking-messages]]; manage page and My bookings [[booking-manage]]; API `api/api-bookings.md`; Workspace record and events `workspace/workspace-booking-record.md`.

## How long are bookings kept?
- Cancelled rows 400 days; the privacy export and erasure cover bookings (#5032): [[booking-retention]].
