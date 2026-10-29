---
name: site-datetime-loader
description: The date-and-time control on site pages, loaded on demand (pg_site_datetime_inject()): the hooks that bring it, what it prints, site mode in pg_datetime.js and pg_fields.js, the library's own CSS
module: site_datetime
---

## When does a site page get the control?
- When its HTML, after every shared component and system widget is drawn in, holds a date hook on an element: `data-pg-date`, `data-pg-datetime`, `data-pg-time` or `data-pg-date-selects` (`pg_site_datetime_pattern()`). `get_page_content.php` calls `pg_site_datetime_inject()` next to `pg_fx_inject()`; never in an e-mail. A page without a hook gets nothing (S1 of `plans/datetime-picker.md`).
- Once a page: the stylesheet's id `pg-datetime-site-css` stops a second injection; a page that already printed the panel tags (`pg_fields_script()`, its `pg_datetime.js`) is left alone.

## What does it print?
- Before `</head>`: vanilla-calendar-pro's own `assets/lib/vanilla-calendar-pro/index.css` (calendar, time row, light and dark themes; sites have no Bootstrap 6 `bootstrap.css` to style `[data-vc=calendar]`) and `assets/css/pg_datetime_site.css` (hidden store, box widths, Done row, popup above a Bootstrap 5 dialog). Not `pg_datetime.css`: it reads panel tokens and sits in a layer that Bootstrap 5's unlayered `.form-control` beats.
- Before the last `</body>`: `get_date_picker_format()` only when the page has no `datetimepicker_options` (a jQuery UI page's own carry the timepicker's words), then `pg_datetime.js` (library address, `data-pg-calendar` = `pg_calendar_settings()` plus `site: true`), then `pg_fields.js`. No `defer`: the fields are drawn on DOMContentLoaded.

## What does site mode change in the scripts?
- `pgDateTime.site()` is true; the Done button takes the tag's `button` classes (`btn btn-sm btn-primary`, Bootstrap 5), else the panel's `btn-solid theme-primary`. `site_datetime.php` and `pg_datetime_site.css` are site files in `bs6_scope_kind()`.
- `pg_fields.js` draws date kinds without `pgUI` or Bootstrap 6, as soon as the library has loaded; the popup stays under `<body>` (no overlay host). Chips and comboboxes are unchanged. A site page without the site tags keeps plain fields.
- The theme follows the nearest `data-bs-theme` (`themeAttrDetect`), else the system: Bootstrap 5.3.8 sites set it on `<html>` (theme switcher).

## What is left to the next steps?
- S1 hooks no renderer: S2 (custom forms, form views, comments) and S3 (checkout) put the hooks on their fields and drop the jQuery UI calls; jQuery UI stays loaded on sites. Every field keeps posting what it posts today (`pg-date-stores.md`).
- Tests: `tests/site_datetime_test.php` (pattern, inject on/off, options kept, the stylesheet's sha256), `tests/pg_fields_test.php` (`dp_site`).

Related: docs/memory/ui/pg-field-wrappers.md, docs/memory/ui/pg-datepicker.md, docs/memory/core/vanilla-calendar-library.md
