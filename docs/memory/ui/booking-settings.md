---
name: booking-settings
description: Settings › Bookings (category bookings): services, staff hours grid and exceptions, booking rules and widget texts in config_text; what the save writes and refuses
module: booking_settings
---

## Where does it live?
- Category `bookings` under Selling (`registry.php`, after Accounting): cards `pgset-booking-services`, `-staff`, `-policy`, `-texts` in `includes/settings/bookings.php`, writes in `bookings.save.php`, page path `settings_bookings.php`, help row `settings_bookings`. Functions in `includes/fn/booking_settings.php` (pure ones tested in `tests/booking_settings_test.php`). Before the 11.0.157 tables (`pg_booking_ready()`) every card shows a note and the save writes nothing.

## Where are the rules and texts stored?
- Not in `config` (full, `data/config-table-no-varchar.md`): two `config_text` rows, `booking_policy` and `booking_texts`, JSON. Read them only through `pg_booking_policy()` / `pg_booking_texts()`, which clean what is stored.
- Policy keys: `lead_minutes` (120), `horizon_days` (60), `cancel_hours` (24, 0 = until the start), `reschedule` (true), `hold_minutes` (10, 2-60), `reminder_hours` ([24, 2], latest first, three at most, 1-168), `auto_confirm` (true; off, a site booking stays `requested`), `step_minutes` (0 = the service's length, else 5-240 on fives). `lead_minutes`, `horizon_days`, `step_minutes` feed `pg_booking_slots()`'s `$policy` as they are.
- Texts: `title`, `intro`, `done`, `taken`; plain text, an empty one is left out and the widget uses its own wording.

## How are services and staff edited?
- Services: one block per row plus an empty New service block (`booking_service[new]`); a typed name adds it. No delete: bookings point at the service, so it is switched off. Price box reads `12,50` and `12.50` alike (`pg_booking_settings_price_cents()`, minor units, `round()`).
- Staff: a panel user (role below 3) picked in `booking_staff_add` is added on save with every enabled service and Mon-Fri 09:00-17:00. Each block posts `booking_staff[<user id>][seen]`, its switch, services (combobox), the weekly grid `h<ISO day>a..d` (two ranges a day, `type="time" data-pg-time="hh:mm"`, week order from `pg_list_period_week_first_day()`), and exception rows `x_from[]`, `x_to[]`, `x_open[]`, `x_close[]`.
- Until midnight: a `type="time"` box ends at 23:59 and cannot hold `24:00`, so an end typed as `00:00` is saved as `24:00` (the day's end), and a stored `24:00` is shown as `00:00` (`pg_booking_settings_box_end()`), in the weekly grid and in exception rows alike; `00:00`-`00:00` is the whole day. Before, an end of `00:00` was refused as not after its start and a stored `24:00` came back as an empty box that refused the next save (`test_booking_settings_hours_until_midnight`).
- Exceptions are typed as a range of days with one range of hours (none: days off) and stored day by day; reading back groups following days with the same hours into one row and drops past days. A row covers a year at most. A stored day with several ranges shows its first, a weekday with more than two its first two (this screen never writes either).
- The copy button copies the first day's row of the grid to every day; the add button clones the block's `<template>` row and calls `pgFields.init(row)`. Both are one delegated listener bound once (`window.pgBookingSettingsBound`).

## What does the save refuse?
- Every box is checked before any write; a refused save writes nothing: a service without a name, a length outside 5-1440, a price above the column, a time range with one box or an end not after its start, an exception row with a bad first day or a last day before it, a user to add who is not a panel user or already staff. Lists are declared in the registry (`booking_service` 2 levels, `booking_staff` 3).
