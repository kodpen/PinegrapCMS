---
name: pg-datepicker-popup-keyboard
description: The popup date calendar from the keyboard: Tab on the field moves on, Tab from its last stop goes past the field, Shift+Tab back, ArrowDown, Escape in a dialog, focus leaving
---

## What does the keyboard do around a popup calendar?
- `popupKeys()` in `assets/js/pg_datetime.js`, bound on the box before the library's own listeners (every `pgDateTime.attach()` without `inline`: panel date, date-and-time and time fields, and site pages that load the control).
- Tab on the field (calendar open): closes it, the browser moves on to the next control. Shift+Tab leaves backwards and closes it (focus leaving).
- ArrowDown (Alt+ArrowDown too) on the field opens a shut calendar; when it is open, the library's Up and Down move focus into it. Left and Right keep moving the text cursor.
- Inside the calendar: Shift+Tab from its first stop goes back to the field (calendar stays open); Tab from its last stop (the day, or Done with a time row) closes it and focuses the control after the field, inside the open `<dialog>` holding the field, else on the page (`stopAfter()`).
- Escape on the field or in the calendar closes it, focus back on the field, with `preventDefault` + `stopPropagation`: a dialog holding the field stays open (before, the same Escape closed the dialog too).
- Focus moving to another element closes it; focus lost to nothing (window blur, a redrawn day, a press on a blank spot) keeps it; an opening that comes after focus moved on (the library opens on a timer after focus) closes again.

## Why?
- #9092: the library takes Tab on the field into the calendar, and the calendar hangs off `<body>` (or the end of the dialog), after everything else. Tab went Previous month, month, year, Next month, the day, then off the page; the rest of the form (Create Gift Card: Quantity, Notes, Create) was never reached, the calendar stayed open over it, and Shift+Tab from its first stop went to the page's last control.
- Drawers draw the calendar after the field instead (`pg-datepicker-drawer.md`); the popup keeps its place on `<body>` / the overlay host so it is not clipped.

## How is it tested?
- `tests/pg_datetime_tab_order_test.php`: Node, a fake document and a stand-in for the library's input mode (Tab and arrows taken into the calendar while shown). Checked by hand in Chromium against Bootstrap 6 and the vendored library: Create Gift Card's order, a dialog, date-and-time through Done.
