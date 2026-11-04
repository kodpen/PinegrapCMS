---
name: booking-widget
description: The booking system widget (#6224): parts by binding, data-pg-booking settings, booking_slots.php (month, day, hold), "anyone" picks the freest, one hold per session
module: widgets_booking
---

## What is it?
- Kind `booking` (`includes/fn/widgets_booking.php`), label "Booking", palette icon `bi-calendar-check`. Loop: one row per bookable service (switched on, a length, at least one staff member who takes it). Row tokens `__service_id`, `__service_name`, `__service_description`, `__service_length` ("45 min"), `__service_price` ('' when 0); static `__service_total`. Static `__privacy_url`. Flags `has_services`, `no_services`, `has_privacy_page`; row `service_has_price`, `service_has_description`.
- Starter: `case 'booking'` in `_buildStarterTree()`; no PHP starter tree: the Salon, Clinic and Law templates place it with `'tree' => 'starter'` ([[booking-templates]]).

## How does the script find its parts?
- By binding, never by class. Section bindings `booking_step_service|staff|when|details|done`, `booking_staff`, `booking_calendar`, `booking_times`, `booking_summary`, `booking_form`, `booking_done`, `booking_status` become `data-pg-booking-part="<name without booking_>"` (`pg_booking_widget_mark()`, list in `pg_booking_widget_parts()`, editor twin `_sdSectionOptions('booking')`). Actions `booking_service` (row button, gets `data-pg-booking-service="<id>"`) and `booking_back` become `data-pg-booking-action`; a bound button is forced to `type="button"`.
- With services the widget is wrapped in `<div data-pg-booking="{json}">`: endpoint, staff per service, zone, hold minutes, words. Same for every visitor, so the page cache may serve it. No services: no wrapper, no script.

## What loads on the page?
- `get_page_content.php` calls `pg_booking_widget_inject()` (`assets/js/pg_booking.js`, defer). `data-pg-booking` is in `pg_site_datetime_pattern()`, so the site date control (library + `pg_datetime.js`) comes too; the calendar is `pgDateTime.month()` (always open, `disableAllDates` + `enableDates`, `days()` swaps the free days and keeps the month).

## What does booking_slots.php answer?
- JSON body only (a cross-site form cannot post it), anonymous. `month` → free days, `day` → times `{start, staff, label}` (label on the site clock, `pg_time_format_code()`), `hold` → `{until, label}` or `error: taken`, `release`. Staff 0 = anyone: `pg_booking_day_times()` gives each start to the free staff member with the fewest busy minutes that day, lowest id on a tie.
- Busy = bookings (`pg_booking_busy_from_rows()`) + Workspace events of the staff user (`ws_events`: people, department, company; all-day ends at 23:59:59 so +1 s), `pg_booking_busy_between()`. Yearly holiday copies are not expanded yet.
- A hold is accepted only on a start the day would offer; one hold per session (`$_SESSION['software']['booking_hold']`), released by a new hold, `release` or Back from the details step.
- Rate limits per address (`pg_booking_throttled()`): reads 120 / 5 min, holds 20 / 10 min, logged as `rate-booking-read|hold`.

## Where does the policy come from?
- `pg_booking_widget_policy()`: Settings › Bookings' `pg_booking_policy()` (lead, horizon, hold, step; `booking_settings.php`) plus the site's zone for the slot engine. The "just taken" words are the site's `pg_booking_texts()['taken']` when set (`pg_booking_widget_taken_text()`).

## How does the visitor book?
- Details form, consent link, confirm, expired and taken paths: [[booking-widget-confirm]] (#6596). The templates' booking pages: [[booking-templates]]. Tests: `tests/booking_widget_test.php`.
