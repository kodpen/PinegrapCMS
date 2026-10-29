---
name: vanilla-calendar-library
description: The vendored vanilla-calendar-pro (assets/lib/vanilla-calendar-pro/, 3.4.0, unmodified) behind the one date-and-time control: why, how it loads once, how to update it
---

## What is it and why is it here?
- vanilla-calendar-pro 3.4.0 (MIT, npm `vanilla-calendar-pro`), the calendar and time picker of the one date-and-time control `assets/js/pg_datetime.js` (decided in #2693, `plans/datetime-picker.md`). It is the version the Bootstrap 6 bundle embeds, so `bootstrap.css` styles its `[data-vc=calendar]` markup unchanged; the bundle's copy is months-only and has no time extension, hence the standalone file.
- `assets/lib/vanilla-calendar-pro/index.js` is the UMD release file byte for byte (sha256 `3104901e…44c554d`, banner `/*! name: vanilla-calendar-pro v3.4.0 | … */`), setting the global `VanillaCalendarPro` with all five extensions (time included); `LICENSE` sits beside it. `index.css` is the package's `styles/index.css` byte for byte (sha256 `856fd50a…f6b0d24`, `tests/site_datetime_test.php`): calendar, time row, light and dark, printed only on site pages (`ui/site-datetime-loader.md`); the panel is styled by `bootstrap.css`. No local change.

## How is it loaded?
- Never by its own tag. `pg_fields_script()` prints `pg_datetime.js` with `data-pg-calendar-src` (the library address) before `pg_fields.js`; `pg_datetime.js` adds the library's tag once and runs waiting fields when it has loaded. A settings card that prints the tags again runs `pg_datetime.js` a second time, which returns at once: the library has no guard of its own.

## How is it updated?
- Copy `index.js`, `LICENSE` and `styles/index.css` (as `index.css`) from the npm package, change the version and the sha256 in `tests/vanilla_calendar_library_test.php` and `tests/site_datetime_test.php`. Move only together with the Bootstrap 6 dist: its global calendar CSS must match the markup of this version (the test checks both carry the same version).

Related: docs/memory/ui/pg-datepicker.md, docs/memory/core/sortablejs-library.md, docs/memory/core/file-header-and-embedded-libraries.md
