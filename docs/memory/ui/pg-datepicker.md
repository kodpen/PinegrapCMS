---
name: pg-datepicker
description: data-pg-date, data-pg-datetime, data-pg-time on the pg_datetime.js calendar, time row in the same popup: own parse, hidden store, clock, keys, theme, dialogs, drawers
---

## How is each kind drawn?
- Date (`data-pg-date`): the calendar of `assets/js/pg_datetime.js` on the posted input itself, which stays visible and typed in (P1 of `plans/datetime-picker.md`). Names in the panel language (`data-pg-locale-label` on the `pg_fields_script()` tag), Monday first when `datetimepicker_options.firstDay` is 1 (Turkish), else Sunday. `="plain"` (ship and delivery dates on `view_order.php`): English, Sunday first.
- Date and time (`data-pg-datetime`, P2 #2757): the posted input is hidden (`.pg-field-native`, visually hidden, `tabindex=-1`); after it one typed box (`.pg-datetime-box`) in the site's date order and clock (`26/10/2026 17:00` with the 24-hour setting). Its popup holds the calendar with the time row under the dates and our Done button; a date click keeps it open; Done, Escape or a click outside close it. The hidden input always gets `26/10/2026 5:00 PM`: an empty box empties it, a date alone is midnight, text that is no date posts as typed for the server to refuse, a time moved before any date waits for the date.
- Time only (`data-pg-time`): the same box (`.pg-time-box`) with the time row alone (layout `<#ControlTime />`, class `pg-vc-time-only`), writing `5:00 PM`. The server keeps reading the same text as before (`form-time-24-hour.md`).
- The box takes the input's classes (`form-control-sm`, `is-invalid`) and is named by its `<label for>` (`aria-labelledby`); its width and the time row's look are in `assets/css/pg_datetime.css` (`@layer custom`), printed by `pg_fields_script()`.

## How does the calendar work?
- `pgDateTime.attach(box, options)` makes a vanilla-calendar-pro `Calendar` (`core/vanilla-calendar-library.md`) in input mode, opened on click or focus. A date pick writes `formatDate()`, fires one `change` and closes; typed text moves it with `set()`, never redrawn. It gets `selectedDates` from `parseDate()`, never reads the box, so `5/3/2026` stays 5 March on a day-first site. `enableDateToggle: false`: a second click keeps the day.
- Keys: the library moves focus into the open calendar on every arrow key; our keydown listener, bound first, stops Left and Right so they move the text cursor.
- Theme: `selectedTheme` from the nearest `data-bs-theme` (else `system`), synced onto the calendar box on init and show; `bootstrap.css` styles every `[data-vc=calendar]`.
- Clock and words: `data-pg-calendar` on the `pg_datetime.js` tag (`pg_calendar_settings()`): `timeMode` 24 when `pg_time_format_code()` is `H:i`, else 12 (#2040: every picker follows the site's clock; the native time box followed the browser), the Done word and the calendar's screen-reader names in `lang()`. The hour goes in zero-padded (`05:00 PM`): the library's 12-hour pattern refuses `5:00 PM` and shows midnight. AM/PM stay English (the library's).

## Dialogs and drawers?
- The popup hangs off `<body>`, under an open `<dialog>`; on show and resize it moves into `pgUI.overlayHost(field)`, placed under the field.
- In a `.drawer` (ACL expiry rows) the calendar is drawn open on a `<div>` in a `.pg-date-inline` wrapper after the field (it takes over its element's classes).
- The library's `destroy()` puts a copy of the input, as it was at creation, in its place; the control puts the real element back at the same index, or the field loses its listeners, `pgField*` state and value (`switchKind()` on the calendar all-day box).

## When is a date field drawn?
- With Bootstrap 6 once its module ran and the library loaded (`pgDateTime.whenReady()`); else plain. Site pages: `site-datetime-loader.md`. No panel screen (#2510-#2639) and no site renderer (#3168) loads the timepicker add-on; `pg_fields.js` reads `window.datetimepicker_options`.
- Tests: `tests/pg_fields_test.php` (`test_pg_fields_dates_on_the_calendar_control`), `tests/vanilla_calendar_library_test.php`, `tests/bs6_timepicker_addon_test.php`.
