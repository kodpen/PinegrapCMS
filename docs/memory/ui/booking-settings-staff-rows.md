---
name: booking-settings-staff-rows
description: Settings › Bookings, the Staff row: members as list rows each edited in a drawer, Add a staff member's drawer and the hours a new member starts with, the drawer a refused staff box opens
module: booking_settings
---

## How is a staff member shown and edited?
- One list row each (their letters `pg-initials`, name, `pg_booking_settings_staff_line()`: "Takes bookings · 3 services · Mon–Fri", or "Takes no bookings"; a run of three days or more is "Mon–Fri", fewer are listed; no hours: "No weekly hours"), Edit. The row keeps the hidden `booking_staff[<id>][seen]` in the pane.
- Edit opens `pgset-booking-member-<id>` (`pg_booking_settings_member_drawer()`): Takes bookings, Bookable services, Weekly hours with "Copy the first day to every day", Days off and special hours with "Add a row", the time zone line and "Holidays are kept in Team and working hours" (link `#settings/workspace_team/pgset-ws-calendar`). Every box carries `form=` and posts under its old name.
- Under 576 px both tables (`.pg-booking-hours`) become blocks: the day across, the four boxes two by two under their labels; nothing scrolls sideways.

## How is a staff member added, and with which hours?
- "Add a staff member" in the Staff row's head (only while a panel user can be added) opens `pgset-booking-member-new`: the picker `booking_staff_add` (Panel user) and the weekly grid `booking_staff[new][h…]`. Save adds the member with every enabled service and the hours shown.
- The hours: the picked user's own working days in Team and working hours (`ws_profiles.workdays`), else the company's week (`ws_workweek()`), else Monday to Friday; each day 09:00-17:00 (`pg_booking_settings_new_member_hours()`). Each option carries its prefill (`data-pg-booking-hours`); a pick fills the grid, which can be changed before Save.
- The save checks the posted grid as a member's grid and stores it as posted (an emptied grid is no hours); a post without the grid gets the prefill. Rows already stored do not change.
- Before: a new member always got Monday to Friday, 09:00 to 17:00, whatever their working days, and the picker sat at the card's foot.

## Which drawer opens on a refused staff box?
- A member's box (`h<day><a-d>`, `x_from`, `enabled`, `services`) opens `pgset-booking-member-<id>`; a refused user to add (`booking_staff_add`) or a new member's hour box (`booking_staff[new][h…]`) opens `pgset-booking-member-new` (`pg_booking_settings_error_drawer()`).

Related: docs/memory/ui/booking-settings-rows.md, docs/memory/ui/booking-settings.md, docs/memory/workspace/workspace-team-settings.md
