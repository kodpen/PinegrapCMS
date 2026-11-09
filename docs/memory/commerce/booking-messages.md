---
name: booking-messages
description: Booking mails, SMS, .ics and reminders: booking.* events, four campaign profile actions (11.0.161), the .ics attachment, SMS kind booking, reminders by the general job without a column
module: booking_messages
---

## Who calls what when a booking changes?
- Every path that writes a booking calls `pg_booking_changed($id, $event)` once after the write (`includes/fn/booking_messages.php`): `created`, `confirmed`, `rescheduled`, `cancelled`. It announces `booking.<event>` (`pg_booking_event_payload()`, no token), then mails and texts the customer.
- `pg_booking_confirm()` calls `pg_booking_created()`: `booking.created`, plus `booking.confirmed` and its mail when confirmed at once. A hold announces nothing.
- `edit_booking.php` calls it when `pg_booking_status_event()` names `confirmed` or `cancelled`; so do the manage page and the API.

## Which mails go out?
- Campaign profile actions `booking_confirmed`, `booking_reminder`, `booking_rescheduled`, `booking_cancelled` (#5032 question 3; step `upgrade_2026_11_0_booking_campaigns()`, 11.0.161, widens `email_campaign_profiles.action` and `email_campaigns.action`). Offered on the profile form once `pg_booking_ready()`, without commerce access; `action_item_id` 0. No profile, no mail.
- `create_auto_email_campaigns()` takes `campaign_item_id`: profiles are matched on item 0, the campaign row keeps the booking id. `pg_email_campaign_mail_properties()` then attaches `pg_booking_ics_attachment()` (read at the send). A contact with the booking's e-mail is passed so a commercial profile respects opt-out.
- Fields (`pg_booking_mail_field_meanings()`, listed under the action on the form): `^^booking_service^^`, `_staff`, `_date` (site date format), `_time` (start – end, site clock), `_zone` (named), `_start` (date and time, takes `%%format%%`), `_place` (organization address), `_name`, `_reference`, `_manage_url` (`booking_manage.php?b=<id>&t=<token>`, [[booking-manage]]).

## Whose name is `staff_name`?
- `pg_booking_staff_public_name()` (pure): the contact's name only, `''` without one, never the login name (#7752: the manage page, mails and .ics showed it). Empty is left out.

## What is in the .ics?
- `pg_booking_ics()` (pure): METHOD:PUBLISH, one VEVENT, UID `booking-<id>@<host>`, UTC times, SEQUENCE `updated_at - created_at`, STATUS CONFIRMED / TENTATIVE / CANCELLED. RFC 5545 escaping, CRLF, folded at 75 octets.

## Which SMS go out?
- Events `booking_confirmed`, `booking_reminder`, `booking_rescheduled`, `booking_cancelled` in `pg_sms_message_events()`, kind `booking` (not commercial) in `pg_sms_kinds()`, off until switched on, shown on the SMS card only once the tables exist (`pg_sms_message_event_shown(…, $booking_on)`). To `bookings.phone`; reference `booking:<id>:<event>:<start_at>`, so a moved booking is told again and a repeated call is not. Fields store, name, service, date, time, manage_url.

## How do reminders go once without a column?
- No `bookings.reminded` column: #5032's shape has none. The general job runs `pg_booking_reminders_run(time())` before the mail and SMS passes. It keeps its last pass time in `config_text` `booking_reminders_at` (claimed before sending, `platform/job-pass-time-claim.md`: a dying pass loses reminders, never doubles them; a first pass looks back 15 min, after a long stop at most a day).
- `pg_booking_reminder_due()` (pure): a reminder's moment is start − hours (`pg_booking_policy()['reminder_hours']`, 24 and 2 while BK2's setting is not there); due when the moment fell in (last, now], the booking is confirmed, not started, and `updated_at` ≤ the moment (booked or changed after it: no reminder). Only the nearest passed moment counts, so after a stop an old 24 h reminder is not sent next to the 2 h one.

## How was it verified?
- `tests/booking_messages_test.php` (step, .ics, DST times, fields, staff name, reminders, SMS, profile actions). No dev database or live provider run.

Related: docs/memory/commerce/bookings-model.md, docs/memory/commerce/subscriptions-mails.md, docs/memory/platform/sms-order-messages.md
