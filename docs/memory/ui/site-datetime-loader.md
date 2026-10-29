---
name: site-datetime-loader
description: The date-and-time control on site pages, loaded on demand (pg_site_datetime_inject()): the hooks that bring it, what it prints, site mode in pg_datetime.js and pg_fields.js, the library's own CSS
module: site_datetime
---

## When does a site page get the control?
- When its HTML, after every shared component and system widget is drawn in, holds a date hook on an element: `data-pg-date`, `data-pg-datetime`, `data-pg-time`, `data-pg-date-selects`, or a bind marker `data-pg-date-bind` (`pg_site_datetime_pattern()`). `get_page_content.php` calls `pg_site_datetime_inject()` next to `pg_fx_inject()`; never in an e-mail. A page without a hook gets nothing (S1 of `plans/datetime-picker.md`).
- Once a page: the stylesheet's id `pg-datetime-site-css` stops a second injection; a page that already printed the panel tags (`pg_fields_script()`, its `pg_datetime.js`) is left alone.

## What does it print?
- Before `</head>`: vanilla-calendar-pro's own `assets/lib/vanilla-calendar-pro/index.css` (calendar, time row, light and dark themes; sites have no Bootstrap 6 `bootstrap.css` to style `[data-vc=calendar]`) and `assets/css/pg_datetime_site.css` (hidden store, box widths, Done row, popup above a Bootstrap 5 dialog). Not `pg_datetime.css`: it reads panel tokens and sits in a layer that Bootstrap 5's unlayered `.form-control` beats.
- Before the last `</body>`: `get_date_picker_format()` only when the page has no `datetimepicker_options` (a jQuery UI page's own carry the timepicker's words), then `pg_datetime.js` (library address, `data-pg-calendar` = `pg_calendar_settings()` plus `site: true`), then `pg_fields.js`. No `defer`: the fields are drawn on DOMContentLoaded.

## What does site mode change in the scripts?
- `pgDateTime.site()` is true; the Done button takes the tag's `button` classes (`btn btn-sm btn-primary`, Bootstrap 5), else the panel's `btn-solid theme-primary`. `site_datetime.php` and `pg_datetime_site.css` are site files in `bs6_scope_kind()`.
- `pg_fields.js` draws date kinds without `pgUI` or Bootstrap 6, as soon as the library has loaded; the popup stays under `<body>` (no overlay host). Chips and comboboxes are unchanged. A site page without the site tags keeps plain fields.
- The theme follows the nearest `data-bs-theme` (`themeAttrDetect`), else the system: Bootstrap 5.3.8 sites set it on `<html>` (theme switcher).

## Which site renderers are hooked?
- S2 (#3132): `get_form_info()` date, date-and-time and time rows (site and panel alike; only the panel prints `picker_scripts`), form item view and form list view boxes, the comment publish time (classic `#publish_date_and_time`, widget `#comment_publish_date_and_time`). All keep site text (`26/10/2026 5:00 PM`, `5:00 PM`); time rows get the time row alone.
- S3a (#3168): checkout and order pages; classic ones hook recurring start, gift card and arrival dates and print no `get_date_picker_format()`.
- A custom layout's markup (custom form screen, cart, express order, billing, shipping address, order form) carries no hook, so they bind by id: `pg_site_datetime_bind(array('date' => ids, 'datetime' => ids, 'time' => ids))` prints `<span hidden data-pg-date-bind="{json}">`; `pg_fields.js` gives each `<input>` with such an id the hook of its kind before drawing (once; a hooked field keeps its own). `prepare_custom_form()` names `time_fields` too.
- Focus (comment publish, the arrival date's radio) goes through `software.focus_date_field()` (`frontend.src.js` and the hand-patched `frontend.min.js`, which terser cannot rebuild byte for byte): `pgFields.focus()` on the box.
- Left: S3b (the order form widget's start date; the unloaded timepicker add-on deleted) and S4. jQuery UI stays loaded on sites.
- Tests: `tests/site_datetime_test.php` (pattern, inject on/off, bind marker, renderer hooks, the stylesheet's sha256), `tests/pg_fields_test.php` (`dp_site`, `dp_bind`), `tests/bs6_timepicker_addon_test.php`.

Related: docs/memory/ui/pg-field-wrappers.md, docs/memory/ui/pg-datepicker.md, docs/memory/core/vanilla-calendar-library.md
