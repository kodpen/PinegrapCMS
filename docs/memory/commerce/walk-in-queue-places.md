---
name: walk-in-queue-places
description: Calendars › Queue (WQ2): the places list, a place's Set-up (six folded rows) and Desks tabs, the place's JSON keys and bounds, Open the queue's checks, Delete and Remove, the QR poster
module: walk_in_queue_places
---

## Where are the places set up?
- Calendars › Queue (`view_queue_places.php`, list table driver) and a place's screen (`edit_queue_place.php`), roles 0-2 (`validate_area_access($user, 'manager')`). The menu link sits after Bookings once `pg_waiting_ready()`. Role-3 staff: `walk-in-queue-desk.md`.
- Code: `includes/fn/walk_in_queue_places.php`; pure parts tested in `tests/walk_in_queue_places_test.php`. Step WQ2 of the walk-in-queue plan.

## How is the screen put together?
- List: Place, Services, Desks open (`1 of 3`), Waiting now (today's `waiting` numbers), the pill (`pg_waiting_place_pill()`: Open teal, Paused amber, Closed grey). "Add place" (fill) opens a drawer asking the name and the first service (letter A, 10 minutes); the place starts closed with the priority groups Elderly, Disabled person, Pregnant. Row menu: "Print the QR poster", Delete (danger).
- A place: name and pill in the head; "Open the queue" / "Close the queue" is quiet beside the tab's one fill (Save on Set-up, "Add desk" on Desks). Tabs Set-up, Desks, Today (the desk screen, `walk-in-queue-desk.md`) and Report (`walk-in-queue-report.md`) (`pg_waiting_place_tabs()`).
- Set-up: name and address, then six folded rows drawn with `pg_settings_fold()` (Site Settings' helper; the screen defines `PG_SETTINGS_ENTRY` to load it): Services, Hours, Taking a number, Messages, Calling order, Keep personal data. A row that keeps the place closed carries "Not set" and opens first. Foot: Save, Cancel.
- Desks: a row each (name, its services, Closed / Paused / "Open · <person>"), Remove (danger, confirm; only on a Closed desk, `pg_waiting_desk_removable()`: a paused one may have a number called to it, #11352). "Add desk" asks the name and the services (all ticked; at least one).

## What does a place's JSON hold?
- `services`: list of `{key, name, letter, minutes}`; keys `s1`, `s2`… stay across saves and a removed key is never given again while a higher one exists (`pg_waiting_keyed_rows()`); letters unique per place, A-Z; minutes 1-240 (the estimate's start until 20 served).
- `hours` / `exceptions`: the bookings' shapes, read with the bookings grid helpers: two ranges a day, rows of days off or special hours.
- `settings` (`pg_waiting_place_settings()`, defaults `pg_waiting_place_defaults()`): `cap` 0-9999 (0 none), `closed_text`, `ways` {qr 1, site 1, kiosk 0}, `party_size`, `staff` (user ids, set on the Desks tab), `keep_days` 1-365 (30).
- `notices`: sms 1, whatsapp 0, email 1, `ahead` 1-20 (3), `texts` {taken, ahead, called}, 500 characters each, '' for the standard text; WQ3 fills `{number}`, `{desk}`, `{link}`.
- Order: `priority` list of `{key p1…, name}` (10 at most; a number's `priority` holds the key), `booking_staff` (booking_staff ids whose bookings join, WQ5), `grace_minutes` 0-60 (10), `not_here_minutes` 1-60 (3), `move_back` 0.

## When does a place open?
- "Open the queue" refuses with "Set these first: …" while `pg_waiting_place_missing()` lists `services`, `hours` (no range in the week), `ways` (none on) or `desks` (none). "Close the queue" sets `closed`; nothing in WQ2 sets `paused`.
- Delete removes the place and its desks, only while no number was taken there today (`day` = `pg_waiting_today()`); its older numbers stay for the report and are cleared at the default keep period.

## Where does the QR poster lead?
- `queue_poster.php?id=<place>`: a printable A4 page: name, address, "Scan to take a number", the QR of `pg_waiting_place_url($id, 'qr')`, the services. The link is `queue.php?place=<id>&from=qr`, the public number page (`walk-in-queue-number-page.md`).

Related: docs/memory/commerce/walk-in-queue-store.md, docs/memory/commerce/walk-in-queue-rules.md, docs/memory/commerce/walk-in-queue-decisions.md
