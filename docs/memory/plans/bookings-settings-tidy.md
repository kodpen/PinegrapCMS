---
name: bookings-settings-tidy
description: Plan (owner 2026-11-21): Site Settings › Bookings as folded rows, services and staff as list rows each edited in a drawer, one "Messages" row saying which booking mails and texts go
priority: 8
---

## What is asked?
- Owner (2026-11-21, in the R&D agent's window): fix the settings screens' contradictions, gather spread settings, make old screens modern. Item 3 of `plans/settings-tidy-census-order.md`; one category a plan (architect, message 11313).

## What contradicts today? (development 0415ddbb72)
- (K1) Every service is an open fieldset of eight to ten boxes and an empty "New service" block is always open under them (`includes/settings/bookings.php:129-209`); a service is added by typing a name there and saving. Display Order is a number box in each.
- (K2) Every staff member is an open fieldset: switch, services, a grid of 28 time boxes and a days-off table (`bookings.php:242-330`); three members fill several screens. A member is added by a picker at the card's foot, only on Save.
- (K3) A new member starts Monday to Friday, 09:00 to 17:00 (`includes/fn/booking_settings.php:686-697`), whatever their own working days in Team and working hours; the pane says neither that nor where public holidays come from, though the free times honour them (`commerce/bookings-model.md`).
- (K4) Reminders and Confirm bookings at once are set here, but whether a mail or a text goes is set in two other places: a campaign profile with a booking action (no profile, no mail: `commerce/booking-messages.md`) and Communication › SMS Messages. The pane names neither: reminders of "24, 2" with neither send nothing.
- (K5) The deposit boxes are drawn only while the store is on (`bookings.php:96`): with Commerce off they vanish without a word, against "a disabled feature's controls stay" (`ui/settings-card-save-rules.md`).
- (K6) Four open cards; `div.switch` drawings (Can be booked, Takes bookings, the two rules); Reminders a comma list with no unit.

## What does the plan settle itself?
- The pane becomes folded rows (`pg_settings_fold()`), as Features (FS2) and Visitors and privacy (VP1): "Services", "Staff", "Booking rules", "Messages", "Widget texts".
- Services and staff become list rows, each opening a drawer of its fields (`pg_settings_drawer()`), as Team and working hours' departments; "Add a service" and "Add a staff member" open an empty drawer; no empty block is drawn.
- A drawer's fields post with the pane's form under today's names; its foot button is the pane's Save (Team's `ws_department_save` precedent). A refused save reopens the drawer holding the box it names.
- Display Order goes: rows keep their order; "Move up" and "Move down" in a row's menu rewrite `booking_service[<id>][order]` (same post name).
- A new member starts on their own working days in Team and working hours, else the Workspace working week (`ws_workweek()`), 09:00 to 17:00; rows already stored do not change.
- The "Messages" row shows, for mails and for texts, which of the four booking events goes, with Change opening the campaign profiles or `#settings/contact/pgset-sms-messages`; it writes nothing (one place per setting, `decisions-made-product-14.md`).
- With Commerce off the deposit boxes are drawn disabled, with a line that deposits are taken as an order; a row without them keeps its stored deposit (`commerce/booking-deposits.md`).

## What waits for the architect?
- Nothing: no schema, no behaviour of a running booking changes, no new part (the parts are on the Design examples screen or come with DX2b).

## What binds it?
- Field names, ids, columns and what a save stores do not change; card ids `pgset-booking-*` and their anchors keep working; one Save for the pane; the save still checks every box before any write.
- `includes/settings/bookings.php` takes one PR at a time.

Related: docs/memory/plans/bookings-settings-tidy-steps.md, docs/memory/plans/bookings-settings-tidy-screens.md, docs/memory/plans/settings-tidy-census-order.md
