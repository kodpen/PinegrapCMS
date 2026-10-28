---
name: pg-datepicker
description: data-pg-date, data-pg-datetime and data-pg-time on the calendar of pg_datetime.js (vanilla-calendar-pro) and <input type="time">: own parse, the hidden store, keys, theme, dialogs and drawers
---

## How is each kind drawn?
- Date (`data-pg-date`): the calendar of `assets/js/pg_datetime.js` on the posted input itself, which stays visible and typed in (P1 of `plans/datetime-picker.md`). Names in the panel language (`data-pg-locale-label` on the `pg_fields_script()` tag), Monday first when `datetimepicker_options.firstDay` is 1 (Turkish), else Sunday. `="plain"` (ship and delivery dates on `view_order.php`): English, Sunday first.
- Date and time (`data-pg-datetime`): the posted input is hidden (`.pg-field-native`, visually hidden, `tabindex=-1`); after it a `.pg-datetime` group with a text box (`.pg-datetime-date`, its own calendar) and an `<input type="time">` (`.pg-time`). Every change writes both into the hidden input as `26/10/2026 5:00 PM`; an empty date empties it, an empty time is midnight, a box that is no date posts as typed for the server to refuse. P2 puts the time into the calendar's popup.
- Time only (`data-pg-time`): the hidden input and an `<input type="time">` after it, writing `5:00 PM`. The server keeps reading the same text as before (`form-time-24-hour.md`).
- Parts take the input's classes (`form-control-sm`, `is-invalid`) and are named by its `<label for>` (`aria-labelledby`).

## How does the calendar work?
- `pgDateTime.attach(box, options)` makes a vanilla-calendar-pro `Calendar` (`core/vanilla-calendar-library.md`) in input mode: it opens on click or focus, a day click writes `formatDate()` into the box, fires one native `change` and closes; a typed date (the box's `change`) moves it with `set()`, never by drawing it again. It gets `selectedDates` from `parseDate()` and never reads the box itself, so `5/3/2026` stays 5 March on a day-first site (Bootstrap 6 Datepicker read it with `new Date()`).
- `enableDateToggle: false`: a second click on the chosen day keeps it, as jQuery UI did.
- Keys: the library moves focus into the open calendar on every arrow key; the control's own keydown listener, bound first, stops Left and Right so they keep moving the text cursor. Escape and an outside click close it, focus back on the box.
- Theme: `selectedTheme` from the nearest `data-bs-theme` (else `system`), synced onto the calendar box on init and show; `bootstrap.css` styles every `[data-vc=calendar]`.

## Dialogs and drawers?
- The library hangs the popup off `<body>`, under an open `<dialog>` in the top layer. On show (and on resize while shown) it moves into `pgUI.overlayHost(field)` and is placed under the field relative to that host, its containing block; the box is `calendar.context.mainElement`.
- Inside a `.drawer` (ACL expiry rows on add/edit user) the calendar is drawn open: not in input mode, on a `<div>` in a `.pg-date-inline` wrapper after the field (the calendar takes over its element's classes, hence the wrapper).
- The library's `destroy()` puts a copy of the input, as it was at creation, in its place; the control puts the real element back at the same index, or the field loses its listeners, `pgField*` state and value (`switchKind()` on the calendar all-day box).

## When is a date field drawn?
- On a page with Bootstrap 6 once its module ran and the library loaded (`pgDateTime.whenReady()`); without Bootstrap 6 the fields stay plain. Site renderers keep jQuery UI until S1-S3 of the plan.
- The timepicker add-on is loaded by no panel screen any more (#2510, #2543, #2623, #2639); `get_*_picker_format()` stays, `pg_fields.js` reads `window.datetimepicker_options`.
- Tests: `tests/pg_fields_test.php` (`test_pg_fields_dates_on_the_calendar_control`), `tests/vanilla_calendar_library_test.php`, `tests/bs6_timepicker_addon_test.php`.
