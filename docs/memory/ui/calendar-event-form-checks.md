---
name: calendar-event-form-checks
description: Calendar event form: whole-number fields and their server check, the location check's time window, the all day label suffix ids, the reservations switch re-running the repeat toggle
---

## Which number fields are checked, and how?
- Repeat count (`total_recurrence_number`, 1 to 999999999), Unpublish days (`unpublish_days`, 0 to 65535, only when Publish is on), Number Of Initial Spots and Remaining Spots (0 to 999999999, only with Limit Reservations): `pg_calendar_event_whole_number_ok()` in `includes/forms/calendar_event_form.php`, digits only. An empty Unpublish or spots field is allowed (never unpublish / no spots).
- The masks are `integer` with `data-inputmask-allowminus` false. Before (#5575) the repeat count and Unpublish had a decimal mask and nothing but the repeat count was checked: the session's non-strict SQL mode stored `2.5` repeats as 3, 99999 days as 65535, `-12` spots as 0.
- Start and End carry the `pg-required-marker` asterisk only; no `required` attribute, because the date-time wrapper hides the posted field and a browser blocks a hidden required field without a visible message.

## What time does the location check get?
- `pg_calendar_event_form_check_window($start, $end)`: both through `prepare_form_data_for_input(…, 'date and time')`, so `Y-m-d H:i`. The non-repeating path once passed the raw value: `strtotime('15/12/2026 10:00')` is false and `05/12/2026` is 12 May, so on day/month sites a room was double-booked (#5571). Tests in `tests/calendar_event_form_test.php`.

## Why are the "& Time" spans `start_time_suffix` / `end_time_suffix`?
- `pg_fields.js` `hideNative()` names a label without an id `<field>_label`; the span had that id, so the all day switch hid the whole Start/End Date label (#5573).

## Who shows the separate reservations row?
- `toggle_calendar_event_recurrence()`, run by the Repeat switch, the separate reservations switch (edit) and the Accept Reservations switch: with Repeat first and Reservations second the row stayed hidden while its ticked box was saved (#5574).
