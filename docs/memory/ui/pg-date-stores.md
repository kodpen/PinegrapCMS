---
name: pg-date-stores
description: What each hooked date/time field keeps (site text, ISO on native inputs, a 24-hour text time, month/day/year selects), how the box writes it, min/max bounds, fields a script builds
---

## Which store does a field keep?
- Decided by the field, never by the screen's code (`storeOf()` in `pg_fields.js`, P3 #2785 of `plans/datetime-picker.md`):
  - site text (a text input): `26/10/2026`, `26/10/2026 5:00 PM`, `5:00 PM`, whatever the clock; read by `validate_date_and_time()`, `validate_time()`, `prepare_form_data_for_input()`.
  - ISO, a native `<input type="date|datetime-local|time">` with `data-pg-date|datetime|time`: `2026-10-26`, `2026-10-26T17:05`, `17:05` (ERP filters, maintenance, page schedule, Workspace). Without JavaScript the native input is what shows.
  - `data-pg-time="hh:mm"`: a text time on the 24-hour clock, `17:05` (waybill `ship_time`).
  - `data-pg-date-selects` on a wrapper: its month, day and year selects (`start_month`, `start_day`, `start_year` as `select_month()`, `select_day()`, `select_year()` print them, or `data-pg-part`) stay the posted fields.
- ISO never goes into a site-text field: `2026-10-26 17:00` passes `validate_date_and_time()` and is stored as `0026-10-2026 17:00` (#2693). `pgDatePicker` and `pgDateTimeField` `get()`/`set()` exchange ISO and write each field in its own store.

## How does the box write it?
- Every store but a site-text date is hidden (`.pg-field-native`) behind one typed box in the site's order and clock (`.pg-date-box`, `.pg-datetime-box`, `.pg-time-box`). An empty box empties the store, a date alone is midnight.
- Text that says no date or time: a text store keeps it as typed for the server to refuse; a native input cannot hold it and is emptied.
- The selects: a pick writes all three and fires a change on each that moved; a typed date the selects cannot hold (a year outside the list) is taken back.

## What bounds the calendar?
- A native input's `min` and `max` (their date part) are the calendar's `dateMin`/`dateMax`. A select triplet runs from 1 January of its first year to 31 December of its last.
- The popup is placed inside its dialog's right edge (it was only kept from the left one).

## How is a field built by a script drawn?
- Put the hooked field in the document, then `pgFields.init(field)` (or a node around it). A select wrapper drawn before its selects are inside stays undrawn until the next `init()`.

Related: docs/memory/ui/pg-datepicker.md, docs/memory/ui/pg-field-wrappers.md
