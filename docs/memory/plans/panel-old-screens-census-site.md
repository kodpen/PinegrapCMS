---
name: panel-old-screens-census-site
description: Census LM1b of old panel screens (pages, files, forms, comments, users, visitors, tools, Site Settings) against look A: what each draws its own way, sizes, six step groups
---

## What does the census cover, and what not?
- LM1b, the content, people and system half of the old panel screens (owner, 2026-11-15: old screens move to the modern look). LM1a holds commerce and ERP (`panel-old-screens-census-commerce.md`).
- In: pages, comments, short links, pop-ups (auto dialogs), menus, regions, ads, the style, theme and design file lists, files, forms, calendars and bookings, users, contacts, campaigns and Messages, visitors and logs, System tools, Site Settings and the settings pages outside the dialog.
- Out: Workspace (PF, AQ), the File Manager, the visual designer and code editors, the form designer `view_fields` (`ui/look-screen-widths.md`), the dashboard (#8938), `visitors.php` (own look, `ui/visitors-screen-look.md`), site endpoints without a panel shell (`seo*.php`, `add_comment.php`).
- It says what is, not a design: the architect makes the plan from it.

## How was it counted? (2026-11-15, development e9ccbec751)
- Per root screen and its `includes/forms/` or `includes/settings/` file: lines; marker lines (`style=`, `<style`, `text-uppercase`, `fg-primary`, `fw-bold`, `btn btn-`, `<table`, `h5`, `small`, `text-muted`); fields (`'type' => 'text'` and the like, `<select>`, `<textarea>`); cards (`card`, `card-header`).
- Estimate of the lines a move touches: markers + 2 per field (its 12-column width) + 2 per card (title and its line) + 12 per list screen (title with count, filters). A lower bound: markup a script or an `includes/fn/` module draws is not counted.
- `tools/check_look.php` counts stylesheets only: of its off-scale rules in `backend.src.css` few belong here (`.monthly_calendar` 7, `.pg-vs-*` 9, `.pg-health-score` 5, `.pg-contact-who` 3); most are Workspace (`.ws-*`) and dashboard widgets.

## What did every screen here already get?
- Widths: every list `pg-measure-list`, every add or edit screen `pg-measure-edit`, tools and settings pages too (#9269, #9330). Lists draw through the table driver (DL5b, #8921); add and edit cards, titles and the floating save bar are look A (DL5c, #8934); the settings dialog and cards (DL5d, #8952); reports (DL5e).

## What is still old on all of them, in shared code?
- Toolbar buttons keep the slot recipes (`btn btn-sm btn-solid theme-primary rounded-pill px-5`, `btn-text theme-secondary`), not `.pg-button`; 44 files write the primary pill, `pg_screen_toolbar()` in `includes/fn/output.php` two more.
- No list prints the sheet's title with its count or a segmented filter: filters are `pg_filter_select()` or `<select>`s (Contacts 10, Submitted Forms 9, Site Log 6).
- The save bar's buttons keep Bootstrap colours; no field sits on the 12-column grid; no card has its line under the title (`ui/look-edit-screens.md`, "not done").
- Settings rows have no icon tile (`ui/look-settings-screens.md`).

## Which steps, in which order?
- One group a step, most used first; estimate in brackets, lines of the files in the group after it. Screens and files per group in the part files.
- S1 Pages, comments, short links, pop-ups, 404 log, accessibility, page tests (620; 12,230): `-content.md`. Done in #9773 (`ui/look-content-screens.md`).
- S2 Users, sessions, contacts, contact groups, privacy, campaigns, Messages (720; 13,880): `-people.md`. Done in #9692 (`ui/look-people-screens.md`).
- S3 Submitted forms, form fields and views, files, calendars, bookings (650; 15,170): `-forms.md`. In progress.
- S4 Menus, regions, ads, styles, themes, design files (430; 9,280), with visitors and logs (320; 3,650): `-content.md`, `-system.md`.
- S5 Site Settings categories (565 without fields, which the dialog CSS already sizes; 10,270): `-settings.md`.
- S6 System tools, Translations, settings pages outside the dialog (825; 14,400): `-system.md`.
