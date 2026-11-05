---
name: calendar-event-form-checks
description: Calendar event form checks: whole numbers, repeat types, text lengths, the all day switch keeping times, the location check time window, the time suffix ids, the reservations row
---

## Which number fields are checked, and how?
- Repeat count (`total_recurrence_number`, 1 to 999999999), Unpublish days (`unpublish_days`, 0 to 65535, only when Publish is on), Number Of Initial Spots and Remaining Spots (0 to 999999999, only with Limit Reservations): `pg_calendar_event_whole_number_ok()` in `includes/forms/calendar_event_form.php`, digits only. An empty Unpublish or spots field is allowed (never unpublish / no spots).
- The masks are `integer` with `data-inputmask-allowminus` false. Before (#5575) the repeat count and Unpublish had a decimal mask and nothing but the repeat count was checked: the session's non-strict SQL mode stored `2.5` repeats as 3, 99999 days as 65535, `-12` spots as 0.
- Start and End carry the `pg-required-marker` asterisk only; no `required` attribute, because the date-time wrapper hides the posted field and a browser blocks a hidden required field without a visible message.

## Which repeat types and text lengths are accepted?
- `recurrence_type` only `day`, `week`, `month`, `year`; `recurrence_month_type` only `day_of_the_month`, `day_of_the_week`. Another value was stored as `''` (enum, non-strict mode) and `get_calendar()` drew the event once per repeat on its start day. A stored row with such a type is now shown once (`$recurrence_number` 0 in `get_calendar()`, `$recurrence` 0 in `get_calendar_event()`).
- Texts longer than their columns are refused, not cut: `pg_calendar_form_check_lengths()` (fn/calendar.php) for the event name and location (100), short description (255), reserve button label (50), the calendar name and the event location name (100).

## What does switching All Day off do to Start and End?
- `toggle_calendar_event_all_day()` keeps each field's time in `data-calendar-time` when it turns the fields into dates; switching off writes the date back with that time (else 00:00 / 23:59) through `pgDateTimeField.set()`. Before, the store kept the date-only text while the box showed 00:00, and the save was refused. The call on page load (fields already date and time) changes nothing.

## What time does the location check get?
- `pg_calendar_event_form_check_window($start, $end)`: both through `prepare_form_data_for_input(…, 'date and time')`, so `Y-m-d H:i`. The non-repeating path once passed the raw value: `strtotime('15/12/2026 10:00')` is false and `05/12/2026` is 12 May, so on day/month sites a room was double-booked (#5571). Tests in `tests/calendar_event_form_test.php`.

## Why are the "& Time" spans `start_time_suffix` / `end_time_suffix`?
- `pg_fields.js` `hideNative()` names a label without an id `<field>_label`; the span had that id, so the all day switch hid the whole Start/End Date label (#5573).

## Who shows the separate reservations row?
- `toggle_calendar_event_recurrence()`, run by the Repeat switch, the separate reservations switch (edit) and the Accept Reservations switch: with Repeat first and Reservations second the row stayed hidden while its ticked box was saved (#5574).
