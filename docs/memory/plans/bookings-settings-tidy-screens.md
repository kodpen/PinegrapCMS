---
name: bookings-settings-tidy-screens
description: Plan bookings-settings-tidy, its screen: the Bookings pane as folded rows, services and staff as list rows with their drawers, the Messages row, the words and what must be true
---

## By which rules?
- `ui/screen-composition-rules.md`; parts copied from the Design examples screen, its section in brackets (`workflow/screen-described-in-plan.md`). "Value rows" comes with DX2b (`plans/design-examples-patterns-mail.md`) before BT3; the rest is there today. No part is new: nothing to ask the architect for a drawing.

## How is the pane laid out?
- Head: "Bookings" and one purpose line [Type and text]: what can be booked, who takes it, the rules and what visitors are told.
- Folded rows [Settings pane], one open (Services, unless an anchor names another), each line saying its state, in this order: "Services" ("3 services, 2 can be booked"), "Staff" ("2 take bookings"), "Booking rules", "Messages", "Widget texts".
- Services: "Add a service" quiet with its plus in the row's head; under it the services as rows [Rows and gathered rows]: icon tile, name, one line ("30 min · 250,00 TRY · Can be booked", or "Switched off"), "Edit" quiet small, a menu [Menus] with "Move up" and "Move down". No service: one line and the add button.
- A service's drawer [Drawer]: Name, Duration, Break before, Break after, Price, Deposit and Deposit product [Fields], Description, "Can be booked" [Fields, switch]; foot: Save the fill. Commerce off: the deposit boxes disabled, their line "Deposits are taken as an order: they wait while Commerce is off".
- Staff: "Add a staff member" quiet in the row's head; the members as rows [Icon tiles and people]: face, name, one line ("Takes bookings · 3 services · Mon–Fri"), "Edit" quiet small.
- A member's drawer: "Takes bookings", Bookable services (combobox), the weekly hours table with "Copy the first day to every day", "Days off and special hours" with "Add a row", the time zone line, and "Holidays are kept in Team and working hours" linking `#settings/workspace_team/pgset-ws-calendar`. The add drawer: the picker, then the weekly hours prefilled from the picked member's working days and changeable before Save.
- Booking rules: Lead time, Booking horizon, Hold, Cancellation window, Times offered, Reminders ("hours before", up to three, comma between) [Fields]; "Visitors may move their booking" and "Confirm bookings at once" [Fields, switch].
- Messages (BT3): one inner card [Value rows], "Mails" and "Text messages", each its sentence ("Sent when confirmed and as a reminder", "Reminder: Not sent") and Change; one meta line: with no reminder mail or text, Reminders send nothing.
- Widget texts: the four boxes as today and their line.
- Under 576 px: rows full width, a row's line under its name, the drawer full width. Foot: Save the fill, one only.

## Which words?
- "Services", "Add a service", "Edit", "Move up", "Move down", "Staff", "Add a staff member", "Messages", "Mails", "Text messages", "Change", "hours before"; never "New service" as a block, "Display Order" or a constant's name.

## What must be true?
- With five services and three staff, the open Services row and the open Staff row each fit one screen at 1440 px; no empty block is drawn.
- Every box posts under today's name; a Save stores what it stored; a refused save opens the drawer of the box it names.
- `#settings/bookings/pgset-booking-services`, `…-staff`, `…-policy`, `…-texts` open their rows.
- With Commerce off a service's deposit boxes are drawn and its stored deposit stays.
- One look at "Messages" says which booking mails and texts go and where each is set.
- The settings search finds "reminder", "working hours" and "deposit".

Related: docs/memory/plans/bookings-settings-tidy.md, docs/memory/plans/bookings-settings-tidy-steps.md
