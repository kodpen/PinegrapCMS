---
name: booking-widget-open-parts
description: Booking widget's twelve parts on the canvas (CB8): steps and details form drawn as their elements, the six parts the script fills drawn as samples from the server's words (settings only)
module: widgets_booking
---

## What did CB8 open?
- Step CB8 of `plans/designer-closed-blocks-steps.md`; the census: [[canvas-closed-blocks-widgets]]. The widget: [[booking-widget]].
- The server never replaces a part's children: `pg_booking_widget_mark()` only adds `data-pg-booking-part`. So the five steps (`booking_step_service|staff|when|details|done`) and `booking_form` were already element trees; the canvas wrongly warned that their children are replaced with backend HTML. They are in `_SD_BOOKING_KEEPS_CHILDREN` now: no warning, drawn as designed.
- The six parts `pg_booking.js` fills (`booking_staff`, `booking_calendar`, `booking_times`, `booking_summary`, `booking_done`, `booking_status`, list `_SD_BOOKING_FILLED`) depend on the visitor's choices and the free slots of the moment: settings only, drawn true on the canvas, never a stored tree.

## What does the canvas draw in a filled part?
- `_sdBookingSectionSample(section, doc)` for an empty part box, inert, with a line saying the page fills it: people buttons ("Anyone available" and two sample names, both with a Turkish form), a month calendar (Monday to Saturday from the 12th free, the 12th picked), six time buttons, the held time, the closing words, the status line.
- The buttons use `SD_BOOKING_BUTTON_CLASS`, the class `pg_booking.js` gives its buttons. The calendar is a stand-in laid out as `pgDateTime.month()` draws it (six weeks, neighbouring days dimmed, weekend red, square cells); its colours follow the page, and the line under it says the site uses the date control's own look.
- Words from the server: `sdDesign.siteWords.booking` = `pg_booking_canvas_sample(policy, texts)`: the script's words (`pg_booking_widget_words()`, shared with `pg_booking_widget_settings()`), hold minutes, zone, the closing words the policy gives (`auto_confirm` booked or requested, the site's own Settings › Bookings text first), six sample times on the site's clock (`pg_booking_time_label()`).
- A filled part that holds children still shows the red warning: the script does replace them.

## What stays closed?
- The calendar's inside (the date control's markup) and the deposit payment box the script puts in the details step: both drawn per visitor by other code.

## Does a running site change?
- Only the status line: `pg_booking.js` joined "Free times on {day}" and "Times are in {zone}." with a space and no full stop; it ends the first sentence with a full stop now, and the canvas joins it the same way. Otherwise same markup, same script; no new setting.
- Seen in the sandbox: the live calendar took the dark look of a dark footer, as VanillaCalendarPro's `themeAttrDetect: '[data-bs-theme]'` reads the first such element of the page; reported to the architect, not changed here.

## How was it checked?
- `tests/booking_widget_open_parts_test.php`: the sample's words equal the script's, the closing words per policy, the times labelled as the slot answers label them, an unknown zone, steps and form printing their children, every part either kept or filled, the button class against `pg_booking.js`, the wiring.
