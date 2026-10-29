---
name: pg-datepicker-drawer
description: The date calendar in a panel drawer: drawn after the field in a hidden .pg-date-inline wrapper, opened on focus, click or ArrowDown, with the popup's surface inside its column
---

## Where does a drawer's calendar go?
- `pg_fields.js` `inlineIn()` passes `inline: true` to `pgDateTime.attach()` for a field inside a `.drawer` (File Manager access drawer ACL expiry rows, API application drawer Security tab `d_expires`).
- `attach()` puts a `.pg-date-inline` wrapper after the field and draws the calendar (`inputMode: false`) on a `<div>` in it: the library takes over its element's classes. It stays in the drawer's flow; a popup would hang off the drawer's scrolling body.

## When is it shown?
- `onDemand()` in `pg_datetime.js`: the wrapper starts `hidden`; focus, a click or ArrowDown (Alt+ArrowDown too) on the field show it, as a popup opens elsewhere.
- Hidden again by a date pick (date mode) and Done (with a time row), both with focus back on the field without opening it again; Escape on the field or in the calendar (`preventDefault` + `stopPropagation`, so the drawer stays open); focus leaving to an element outside field and calendar.
- A focus loss to nothing (`relatedTarget` null) right after a press inside the calendar keeps it open: Safari does not focus a clicked button and the library redraws the days under the pointer. Any other loss to nothing hides it.

## How is it drawn?
- `pg_datetime.css` (`@layer custom`, which comes after Bootstrap's `components` layer that strips a non-input calendar's border and shadow): the popup's surface (`--pg-bg-body`, `--pg-border-color-translucent` border, `--pg-radius-lg`, `--pg-shadow`), `width: fit-content`, `max-width: 100%`, `overflow-x: auto`, `.5rem` padding and 1.5rem day cells, so a month fits a half-width drawer column; a narrower one scrolls it sideways, never over the next column.
- `.pg-date-inline[hidden]` takes no room.

## What did the fix change?
- Before #3267 the calendar was drawn open for good as soon as the drawer tab showed, with no surface, wider than its `col-6`, over the next field's help text (API application drawer, Security tab).
- Tests: `tests/pg_datetime_drawer_test.php` (Node run of `pg_datetime.js` against a fake document, and the CSS rule); `tests/pg_fields_test.php` `dp_drawer` keeps the wrapper and `inputMode: false`.
