---
name: pg-datepicker
description: data-pg-date, data-pg-datetime and data-pg-time on Bootstrap 6 Datepicker and <input type="time"> (B9c, #2262): own parse, the hidden store, the clone dispose leaves, dialogs and drawers
---

## How is each kind drawn?
- Date (`data-pg-date`): Datepicker on the posted input itself, which stays visible and typed in. Names in the panel language (`data-pg-locale-label` on the `pg_fields_script()` tag), Monday first when `datetimepicker_options.firstDay` is 1 (Turkish), else Sunday. `="plain"` (ship and delivery dates on `view_order.php`): English, Sunday first.
- Date and time (`data-pg-datetime`): the posted input is hidden (`.pg-field-native`, visually hidden, `tabindex=-1`); after it a `.pg-datetime` group with a text box (`.pg-datetime-date`, own Datepicker) and an `<input type="time">` (`.pg-time`). Every change writes both into the hidden input as `26/10/2026 5:00 PM`; an empty date empties it, an empty time is midnight, a box that is no date posts as typed for the server to refuse.
- Time only (`data-pg-time`): the hidden input and an `<input type="time">` after it, writing `5:00 PM`. The server keeps reading the same text as before (`form-time-24-hour.md`).
- Parts take the input's classes (`form-control-sm`, `is-invalid`) and are named by its `<label for>` (`aria-labelledby`).

## Why never Bootstrap's own reading of the date?
- Created on an input, Datepicker reads its value with `new Date()`: `5/3/2026` became May 3 on a day-first site. The input is emptied while the instance is created and gets its text back; the date goes in as `selectedDates` from `parseDate()`, and `dateFormat` is a function writing `formatDate()`. `enableDateToggle: false`: a second click on the chosen day keeps it, as jQuery UI did.
- Datepicker moves its month only when created, so a typed date (the box's `change`) or `set()` disposes and creates it again.

## What does dispose do to the input?
- vanilla-calendar-pro's destroy puts a clone of the input, as it was at creation, in its place. `disposeDatepicker()` puts the real element back at the same index, or the field loses its listeners, `pgField*` state and current value (`switchKind()` on the calendar all-day box would lose it).

## Dialogs and drawers?
- The popup hangs off `<body>`, under an open `<dialog>` in the top layer. On click, focus and resize (after Bootstrap placed it) the calendar box moves into `pgUI.overlayHost(field)` and is placed under the field relative to that dialog, its containing block. The box is only reachable as `picker._calendar.context.mainElement`.
- Inside a `.drawer` (ACL expiry rows on add/edit user) the calendar is drawn open: `inline: true` on a `<div>` in a `.pg-date-inline` wrapper after the field (the calendar takes over its element's classes, hence the wrapper).

## What else changed?
- No engine is left for a page with Bootstrap 5: the fields stay plain there, like chips. The jQuery UI date paths of `pg_fields.js` and their `inOverlayHost()` are gone (and their entries in `tools/bs6_switch_texts.txt`). Site renderers keep jQuery UI; its panel CSS (`.ui-datepicker`, `#ui-datepicker-div`) goes with B12.
- The jQuery UI timepicker add-on is no longer loaded by the panel screens or the backend `picker_scripts` of `get_form_content()` (B12, #2510); the `get_*_picker_format()` calls stay, `pg_fields.js` reads `window.datetimepicker_options`. Still loading it: add/edit_calendar_event.php, add/edit_submitted_form.php, view_order.php, includes/settings/commerce.php. The site renderers keep it.
- Tests: `tests/pg_fields_test.php` (`test_pg_fields_dates_on_bootstrap_6`), `tests/bs6_timepicker_addon_test.php`.
