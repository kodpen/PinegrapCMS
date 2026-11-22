---
name: bookings-settings-tidy-steps
description: Plan bookings-settings-tidy, steps BT1 to BT4: folded rows and services in drawers, staff in drawers, the rules and the Messages row, close-out
---

## Which steps are there?
- BT1 (M) first, done (#11008): the pane as folded rows (`pg_settings_fold()`) "Services", "Staff", "Booking rules", "Widget texts", each with its state line; the services as list rows, each opening its drawer (`pg_settings_drawer()`) of today's boxes; "Add a service" quiet in the row's head opens an empty drawer (`booking_service[new]`, as today). Fixes: K1, K5, K6's drawing.
- In BT1 Display Order leaves the drawer: "Move up" and "Move down" in a row's menu rewrite the rows' `booking_service[<id>][order]`; with Commerce off the deposit boxes are drawn disabled with their line; every switch is `pg-switch`; the services' keywords gain "deposit". Each card keeps its id inside its row.
- In BT1 a refused save reopens the drawer holding the box it names: one pure `pg_booking_settings_error_drawer()` in `includes/fn/booking_settings.php` (error key and row to drawer id), red test first in `tests/booking_settings_test.php`.
- Tests of BT1: `tests/settings_card_ids_test.php` and `settings_card_names_test.php` with the ids kept; every box posts under its old name; `bookings.save.php` unchanged but for the error's drawer.

- BT2 (M) after BT1, done (#11089): the staff as list rows (face, name, one line: takes bookings or not, how many services, the days worked), each opening its drawer: Takes bookings, the services, the weekly hours, days off and special hours, with "Holidays are kept in Team and working hours" linking `#settings/workspace_team/pgset-ws-calendar`. "Add a staff member" opens a drawer with the picker and the weekly hours; its Save adds the member with the hours shown. Fixes: K2, K3.
- In BT2 the add drawer's hours are prefilled when a member is picked: their own working days in Team and working hours, else `ws_workweek()`, else Monday to Friday, 09:00 to 17:00 (one pure function, red test first); the hours posted are saved as shown, through the same checks as a member's grid; rows already stored do not change.

- BT3 (S) after BT2 and DX2b, done (#11133) (`plans/design-examples-patterns-mail.md`, "Value rows"): "Booking rules" with Reminders as a box with its unit ("hours before") and help; the "Messages" row (`pgset-booking-messages`, new, in the registry with keywords).
- In BT3 the Messages row holds two value rows, "Mails" and "Text messages", each naming which of Confirmed, Reminder, Moved and Cancelled goes (a reminder nothing sends: "Not sent"), Change opening `view_email_campaign_profiles.php` or `#settings/contact/pgset-sms-messages`. Fixes: K4.
- In BT3 the values come from one pure `pg_booking_settings_messages()` (the events with a profile switched on, the SMS events switched on), red test first; the row writes nothing.

- BT4 (S) close-out: `ui/booking-settings.md` rewritten (the rows, the drawers, the Messages row, a new member's days); `ui/look-commerce-settings-screens.md` corrected where it names the Bookings fieldsets; item 3 of `plans/settings-tidy-census-order.md` removed; this plan's files deleted.

## Which steps run side by side?
- None: BT1, BT2 and BT3 enter `includes/settings/bookings.php` and `registry.php` one after another.

## How is a step checked?
- A step copies its parts from the Design examples screen and writes no recipe of its own; it renders the pane at 1440 and 390 px with two services and two staff before ready and writes "Screen check" in the PR.

Related: docs/memory/plans/bookings-settings-tidy.md, docs/memory/plans/bookings-settings-tidy-screens.md
