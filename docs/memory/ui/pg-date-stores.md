---
name: pg-date-stores
description: What each hooked date/time field keeps (site text, ISO on native inputs, a 24-hour text time, month/day/year selects), how the box writes it, min/max bounds, fields a script builds
---

## Which store does a field keep?
- Decided by the field, never by the screen's code (`storeOf()` in `pg_fields.js`, P3 #2785 of `plans/datetime-picker.md`):
  - site text (a text input): `26/10/2026`, `26/10/2026 5:00 PM`, `5:00 PM`, whatever the clock; read by `validate_date_and_time()`, `validate_time()`, `prepare_form_data_for_input()`.
  - ISO, a native `<input type="date|datetime-local|time">` with `data-pg-date|datetime|time`: `2026-10-26`, `2026-10-26T17:05`, `17:05` (ERP filters, maintenance, page schedule, Workspace). Without JavaScript the native input is what shows.
  - `data-pg-time="hh:mm"`: a text time on the 24-hour clock, `17:05` (waybill `ship_time`).
  - `data-pg-date-selects` on a wrapper: its month, day and year selects (`start_month`, `start_day`, `start_year` as `select_month()`, `select_day()`, `select_year()` print them, or `data-pg-part`) stay the posted fields. The box is named by the wrapper's first `<label>` or `<legend>`; list screens' ranges are `<fieldset data-pg-date-selects>` (P7 #2862).
- ISO never goes into a site-text field: `2026-10-26 17:00` passes `validate_date_and_time()` and is stored as `0026-10-2026 17:00` (#2693). `pgDatePicker` and `pgDateTimeField` `get()`/`set()` exchange ISO and write each field in its own store.

## How does the box write it?
- Every store but a site-text date is hidden (`.pg-field-native`) behind one typed box in the site's order and clock (`.pg-date-box`, `.pg-datetime-box`, `.pg-time-box`). An empty box empties the store, a date alone is midnight.
- Text that says no date or time: a text store keeps it as typed for the server to refuse; a native input cannot hold it and is emptied.
- Focus a hooked field with `pgFields.focus(el)`: it lands on the box (whose calendar opens on focus), never on the hidden store (comment publish schedule, #2832).
- The selects: a pick writes all three and fires a change on each that moved; a typed date the selects cannot hold (a year outside the list) is taken back.

## What bounds the calendar?
- A native input's `min` and `max` (their date part) are the calendar's `dateMin`/`dateMax`. A select triplet runs from 1 January of its first year to 31 December of its last.
- The popup is placed inside its dialog's right edge (it was only kept from the left one).

## How is a field built by a script drawn?
- Put the hooked field in the document, then `pgFields.init(field)` (or a node around it). A select wrapper drawn before its selects are inside stays undrawn until the next `init()`.
- A script that writes a drawn field's store calls `pgFields.refresh(el)` (value and disabled state, no change: designer page schedule, #2854) or fires a change on it; a bare `.value =` leaves the box stale. The box takes the field's `aria-label`, `placeholder` and `title`.

## Which panel fields reach the control outside the shared forms (P4)?
- The form preview's `time` row (`get_form_info()`, panel only) has `data-pg-time`; `pg_form_info_picker_format()` gives the options its hooked rows need. Site rows carry the same hooks (`site-datetime-loader.md`).
- List filter rows (`update_value_cell()` in `backend.src.js`, drawn where the screen prints `pg_fields_script()`: the form list view; the order and visitor reports wait for P8): the value box of a date, date-and-time or time field gets its hook (site text); the old box's control is destroyed before the cell is redrawn, and a dynamic value empties it through `pg_filter_value_clear()`.
- A box with `data-pg-add-to-chips="<id>"` adds a picked date to those chips once and empties itself (shipping method excluded dates; it has no name, so it does not post).
- The commerce settings' hour-of-day select (`settings/prep.php` options, `%02d:00`) still ignores the clock: left for P8.

Related: docs/memory/ui/pg-datepicker.md, docs/memory/ui/pg-field-wrappers.md
