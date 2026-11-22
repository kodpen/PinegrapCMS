---
name: booking-settings-rows
description: Settings › Bookings as folded rows with lines of state; services and staff as list rows edited in drawers, Move up and Move down, a new member's hours, the drawer a refused save opens
module: booking_settings
---

## How is the pane laid out?
- Four folded rows (`pg_settings_fold()`, `ui/settings-fold-rows.md`), each holding its card with its id: Bookable services, Staff, Booking rules, Booking widget texts. Lines from `pg_booking_settings_fold_lines()`: "3 services, 2 can be booked" (none: "No service yet"), "2 take bookings", "Up to 60 days ahead · confirmed at once", "1 of 4 texts set" (none: "The widget's own wording"). Bookable services opens first; an anchor opens its row. Before the tables every row holds the upgrade note.
- Row names keep the registry's section labels: "Bookable services", not "Services", whose Turkish key reads as a shuttle (`test_booking_settings_pane_labels_and_reminder_keyboard`).
- Switches are look A's `pg-switch` (Can be booked, Takes bookings, the two rules). The services' search keywords include "deposit".

## How is a service shown and edited?
- One list row each (icon tile, name, `pg_booking_settings_service_line()`: "30 min · 250,00 TRY · Can be booked" or "Switched off", Edit, a menu with Move up and Move down).
- Edit opens the service's drawer `pgset-booking-service-<id>` (`pg_settings_drawer()`, `ui/settings-drawer-card-form.md`): Name, Duration, the two breaks, Price, Deposit and its product, Description, Can be booked; its boxes carry `form=` and post as `booking_service[<id>][…]`; its foot Save is the pane's Save.
- "Add a service" in the row's head opens `pgset-booking-service-new` (`booking_service[new]`); a typed name adds it, an empty one is passed over. No empty block is drawn (#11008).

## How is a staff member shown and edited?
- One list row each (their letters `pg-initials`, name, `pg_booking_settings_staff_line()`: "Takes bookings · 3 services · Mon–Fri", or "Takes no bookings"; a run of three days or more is "Mon–Fri", fewer are listed; no hours: "No weekly hours"), Edit. The row keeps the hidden `booking_staff[<id>][seen]` in the pane.
- Edit opens `pgset-booking-member-<id>` (`pg_booking_settings_member_drawer()`): Takes bookings, Bookable services, Weekly hours with "Copy the first day to every day", Days off and special hours with "Add a row", the time zone line and "Holidays are kept in Team and working hours" (link `#settings/workspace_team/pgset-ws-calendar`). Every box carries `form=` and posts under its old name.
- Under 576 px both tables (`.pg-booking-hours`) become blocks: the day across, the four boxes two by two under their labels; nothing scrolls sideways.

## How is a staff member added, and with which hours?
- "Add a staff member" in the Staff row's head (only while a panel user can be added) opens `pgset-booking-member-new`: the picker `booking_staff_add` (Panel user) and the weekly grid `booking_staff[new][h…]`. Save adds the member with every enabled service and the hours shown.
- The hours: the picked user's own working days in Team and working hours (`ws_profiles.workdays`), else the company's week (`ws_workweek()`), else Monday to Friday; each day 09:00-17:00 (`pg_booking_settings_new_member_hours()`). Each option carries its prefill (`data-pg-booking-hours`); a pick fills the grid, which can be changed before Save.
- The save checks the posted grid as a member's grid and stores it as posted (an emptied grid is no hours); a post without the grid gets the prefill. Rows already stored do not change.
- Before: a new member always got Monday to Friday, 09:00 to 17:00, whatever their working days, and the picker sat at the card's foot.

## How is the order changed?
- No Display order box any more. Each row posts a hidden `booking_service[<id>][order]` in the pane; Move up / Move down swap the row, rewrite every row's order from 0 and submit the form (a save at once: hidden inputs do not make the dialog's Save active). The add drawer's order is one past the highest.

## Which drawer opens on a refused save?
- `bookings.save.php` names the drawer of the first refused service box with `pg_settings_drawer_refused(pg_booking_settings_error_drawer('booking_service', $key, $error))`; `settings_pane.php` answers it as `drawer`, and the dialog's `refused(fields, drawer)` opens it (`reopen()`) and focuses its marked box. The page path opens the shut drawer of the first marked field itself (`pgSettingsRestore()`). A staff box (`h<day><a-d>`, `x_from`, `enabled`, `services`) opens the member's drawer; a refused user to add or a new member's hour box opens the add drawer (`test_booking_settings_error_drawer`).

Related: docs/memory/ui/booking-settings.md, docs/memory/ui/settings-drawer-card-form.md
