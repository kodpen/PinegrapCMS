---
name: panel-old-screens-census-site-forms
description: Census LM1b against look A (S3): submitted forms, form fields, the form list and item views, files (Create File, Edit File, Import Zip), calendars, events, locations, bookings
---

## How is a line read?
- `file`: lines / marker lines / `style=` / fields / cards → estimate; method in `panel-old-screens-census-site.md`.

## Which forms screens, and what is old? (325; 8,160 lines)
- Submitted Forms list `view_submitted_forms.php` 813 / 16 / 3 / 20 / 3 → 74: Advanced Filters with `fg-success fw-bold` headings, nine Bootstrap buttons, nine selects.
- `add_submitted_form.php` → 7, `edit_submitted_form.php` 1,030 → 5, `includes/forms/submitted_form_form.php` 1,105 / 4 / 2 / 8 / 4 → 28.
- Form fields: `add_field.php` clean, `edit_field.php` → 6, `includes/forms/field_form.php` 1,466 / 8 / 7 / 15 / 14 → 66.
- Form list view `edit_form_list_view.php` 1,319 / 36 / 25 / 17 / 9 → 88: 25 inline styles (display, position, min sizes), an own table, `fw-bold fg-secondary` labels.
- Form item view: `edit_form_item_view.php` → 1, `includes/forms/form_item_view_form.php` 320 / 14 / 3 / 1 / 6 → 28, a 13-line `<style>`, five Bootstrap buttons.
- Import Submitted Forms `import_submitted_forms.php` 515 → 22 (upper-case titles).

## Which files screens, and what is old? (115; 2,910 lines)
- Upload `add_file.php` 661 → 12; Edit File `edit_file.php` 1,307 / 18 / 5 / 3 / 14 → 52 (seven Bootstrap buttons, an upper-case title); Create File `create_file.php` 260 / 8 / 2 / 5 / 8 → 34 (four upper-case titles); Import Zip `import_zip.php` 681 → 18.
- `view_folder_and_files.php` sends on to the File Manager; `edit_files.php` is an endpoint.

## Which calendar and booking screens, and what is old? (210; 4,090 lines)
- Lists: `view_calendars.php` → 13, `view_calendar_event_locations.php` → 12, the month view `calendars.php` → 14 (#9230: not on the driver; `.monthly_calendar` has 7 off-scale rules in `backend.src.css`).
- Calendar and location forms: `includes/forms/calendar_form.php` → 13, `calendar_event_location_form.php` → 13; their add and edit wrappers clean.
- Events: `includes/forms/calendar_event_form.php` 1,087 / 14 / 12 / 17 / 14 → 76 (inline `display` and `position`); `add_calendar_event.php` → 1, `edit_calendar_event.php` 641 → 2.
- Bookings `view_bookings.php` 243 / 11 / 0 / 1 / 3 → 31 (six Bootstrap buttons, its own title); Edit Booking `edit_booking.php` 588 / 7 / 3 / 4 / 10 → 35.
