---
name: booking-settings
description: Settings › Bookings: five folded rows, services and staff in drawers, a new member's days, the Messages row, rules and texts in config_text, what the save refuses
module: booking_settings
---

## Where does it live?
- Category `bookings` under Selling (`registry.php`, after Accounting); `includes/settings/bookings.php`, writes in `bookings.save.php`, page path `settings_bookings.php`, help row `settings_bookings`. Functions in `includes/fn/booking_settings.php` (pure ones tested in `tests/booking_settings_test.php`). Before the 11.0.157 tables (`pg_booking_ready()`) every row holds a note and the save writes nothing.

## How is the pane put together?
- Five folded rows, each with its line of state and its card id kept: Bookable services (`pgset-booking-services`), Staff (`-staff`), Booking rules (`-policy`), Messages (`-messages`), Booking widget texts (`-texts`); one Save for the pane (`ui/booking-settings-rows.md`). Before (#11008 to #11133) the pane was four open cards with a fieldset a service and a member.
- Services and staff are list rows, each edited in a drawer whose boxes post with the pane's form under the old names; "Add a service" and "Add a staff member" open an empty drawer; a refused save opens the drawer of the box it names (`ui/booking-settings-rows.md`, `ui/booking-settings-staff-rows.md`).
- A new member's hours come from their own working days in Team and working hours, else the company's week, 09:00-17:00, shown in the add drawer and changeable before Save (`ui/booking-settings-staff-rows.md`).
- Messages says which booking mails and texts go and links where each is set; it writes nothing (`ui/booking-settings-messages.md`).
- With Commerce off a service's Deposit and Deposit product are drawn disabled with "Deposits are taken as an order: they wait while Commerce is off."; a disabled box is not posted, so the stored deposit stays (`commerce/booking-deposits.md`).

## Where are the rules and texts stored?
- Not in `config` (full, `data/config-table-no-varchar.md`): two `config_text` rows, `booking_policy` and `booking_texts`, JSON. Read them only through `pg_booking_policy()` / `pg_booking_texts()`, which clean what is stored.
- Policy keys: `lead_minutes` (120), `horizon_days` (60), `cancel_hours` (24, 0 = until the start), `reschedule` (true), `hold_minutes` (10, 2-60), `reminder_hours` ([24, 2], latest first, three at most, 1-168), `auto_confirm` (true; off, a site booking stays `requested`), `step_minutes` (0 = the service's length, else 5-240 on fives); the first three feed `pg_booking_slots()`'s `$policy` as they are.
- Texts: `title`, `intro`, `done`, `taken`; plain text, an empty one is left out and the widget uses its own wording.

## What do services and staff post?
- No delete of a service: bookings point at it, so it is switched off. Price reads `12,50` and `12.50` alike (`pg_booking_settings_price_cents()`, minor units, `round()`).
- A member posts `booking_staff[<user id>]…`: switch, services, the weekly grid `h<ISO day>a..d` (two ranges a day, week order from `pg_list_period_week_first_day()`), exception rows `x_from[]`, `x_to[]`, `x_open[]`, `x_close[]`, and `[seen]` from its row.
- How hours and exceptions are typed and stored (until midnight, day ranges): `ui/booking-settings-staff-rows.md`.

## What does the save refuse?
- Every box is checked before any write; a refused save writes nothing: a service without a name, a length outside 5-1440, a price above the column, a time range with one box or an end not after its start, a bad exception row, a user to add who is not a panel user or already staff. Lists are declared in the registry (`booking_service` 2 levels, `booking_staff` 3).

Related: docs/memory/ui/booking-settings-rows.md, docs/memory/ui/booking-settings-staff-rows.md, docs/memory/ui/booking-settings-messages.md
