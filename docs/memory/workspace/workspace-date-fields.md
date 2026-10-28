---
name: workspace-date-fields
description: Workspace date and time fields on the one control: pgDateField(), the watcher that draws them, script-written values, the control in the lazy record loader (#2867)
---

## How does a Workspace form get a date or time field?
- Build the native input with `pgDateField(input, 'date' | 'time' | 'datetime-local')` (`assets/js/workspace.js`): it sets the type and the `data-pg-*` hook. The input stays the ISO store every post reads (`2026-10-26`, `17:05`, `2026-10-26T17:05`); the control (`ui/pg-datepicker.md`) draws one typed box after it in the site's date order and clock.
- Never set `.type = 'date'` (or `time`, `datetime-local`) directly: `tests/workspace_datetime_fields_test.php` counts the fields that go through `pgDateField()`.
- The bundle's modules (approvals, customer, recurrence, task time, task approval) get it as `h.dateField` from `partHelpers()`, `featureHelpers()` and the repeat field's helpers (P10).
- The timeline toolbar has its own local `dateField(key, label)`; the helper is named `pgDateField` so it is not shadowed there.

## When is the box drawn?
- `watchDateFields()`, started by `start()`, observes `document.body`: any added node holding a hooked field is passed to `pgFields.init()`. Forms are built off the page and added later (drawers, dialogs, panels), so no call site draws its own fields.
- A page without Bootstrap 6 or without `pg_fields.js` keeps the native inputs.

## What if a script writes a field's value?
- Fire a change on it (the box follows; `workspace_recurrence.js` writing the due date) or call `dateShown(input)` / `pgFields.refresh(input)` (no change: the timeline's Clear, the start date the repeat box moves). A bare `.value =` leaves the box stale. A field shown and hidden by a script sits in a wrapper that is hidden instead (the repeat's end date), since its box is a sibling; focus it with `pgFields.focus()`.

## How do the control's files reach the screen?
- Workspace screens print `get_date_picker_format() . pg_fields_script()` before `workspace.min.js` (`ws_screen_assets()`), unless the screen printed the control already.
- A record's screen (`ws_record_loader()`, `core/js-bundles.md`) keeps `get_date_picker_format() . pg_fields_script_tags()` in `<template id="ws-fields-assets">`; the first click loads them before the bundle when `window.pgFields` is missing, each script made again with its attributes (calendar settings, labels). `pg_fields_script_tags()` has no once-per-request guard, so the screen's own later `pg_fields_script()` still prints.

Related: docs/memory/ui/pg-date-stores.md, docs/memory/core/js-bundles.md, docs/memory/plans/datetime-picker-steps.md
