---
name: decisions-made-panel
description: Decisions list, panel part: Bootstrap 6 breakpoints (1024/1280, xxl to 2xl) kept on purpose; the one date-and-time picker (vanilla-calendar-pro); the main menu in category headings
---

## Why do the panel's own media queries break at 1024 and 1280 px?
- Owner, 2026-10-27, at the Bootstrap 6 switch (#1866), answering whether they should stay on 992/1200: "önerilerini destekliyorum". Bootstrap 6 moved `lg` to 1024 and `xl` to 1280 px; the panel's own queries moved with them, so they change at the same width as the `lg:`/`xl:` classes on the same screen. Between 992 and 1023 px the menu and layouts show one step smaller than under Bootstrap 5. Not a wrong breakpoint.

## Why do the product and user screens use the narrower layout at 1440 px?
- Owner, same day and words: Bootstrap 5's `xxl` (1400 px) classes follow the official rule to `2xl` (1536 px), not to the panel's `xl` (1280 px). From 1400 to 1535 px those screens use the `xl` column split (the product form a little narrower, the preview column wider). Not a lost breakpoint; do not map `2xl` back to `xl`.
- The official v5 to v6 rules stay the codemod's rules (`ui/bootstrap-6-codemod.md`); a visual difference they bring is fixed forward, not by a second mapping.

## Which date-and-time picker does the software use? (#2693, 2026-10-28)
- One control for the whole software, the time inside the same popup, following the site's date order and 24-hour setting. Engine (architect): vanilla-calendar-pro 3.4.0 vendored standalone under `assets/lib/` (the version Bootstrap 6 embeds; its dist stays unchanged), driven by `assets/js/pg_datetime.js` through the `pg_fields.js` hooks; every field keeps its posted text exactly (classic `d/m/Y h:mm AM/PM`, ISO on native stores, select triplets). Ranges stay two fields, each with the control; a range calendar may come later as its own step.
- Owner: the sites are in, in stages with a stop after each: panel (P1-P11), then the classic renderers (custom forms, form views, comments, checkout, order form), then the designer widgets and form palette; Bootstrap 5.3.8 and jQuery UI stay loaded on sites. On phones too: the one control, never the OS picker, since only the control follows the site's settings. Plan: `plans/datetime-picker.md`.

## How is the main menu grouped? (owner, 2026-10-28)
- Categories as headings over the rows, never one row per category (Ecommerce stays five rows); a heading only over rows the person gets, also over a single one; Calendars and Forms stay in Content beside Pages and the File Manager; the File Manager row is the destination, the Files row is retired; the store rows lose "All" (`ui/main-menu-categories.md`). Not an empty-heading bug or a lost Files row.
