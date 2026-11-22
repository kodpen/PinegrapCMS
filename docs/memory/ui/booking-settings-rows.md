---
name: booking-settings-rows
description: Settings › Bookings as folded rows with lines of state; services as list rows edited in drawers, Move up and Move down, the drawer a refused save opens
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

## How is the order changed?
- No Display order box any more. Each row posts a hidden `booking_service[<id>][order]` in the pane; Move up / Move down swap the row, rewrite every row's order from 0 and submit the form (a save at once: hidden inputs do not make the dialog's Save active). The add drawer's order is one past the highest.

## Which drawer opens on a refused save?
- `bookings.save.php` names the drawer of the first refused service box with `pg_settings_drawer_refused(pg_booking_settings_error_drawer('booking_service', $key, $error))`; `settings_pane.php` answers it as `drawer`, and the dialog's `refused(fields, drawer)` opens it (`reopen()`) and focuses its marked box. The page path opens the shut drawer of the first marked field itself (`pgSettingsRestore()`). Staff boxes are drawn in the pane, so no drawer (`test_booking_settings_error_drawer`).

Related: docs/memory/ui/booking-settings.md, docs/memory/ui/settings-drawer-card-form.md
