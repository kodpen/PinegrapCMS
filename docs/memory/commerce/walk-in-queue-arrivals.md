---
name: walk-in-queue-arrivals
description: Walk-in queue arrivals (WQ5): priority from staff, the kiosk and the desk row; I have a booking (today's booking by phone or e-mail); I'm on my way; where a moved-back number stands
module: walk_in_queue_arrivals
---

## Where is a number's priority given?
- Staff's "Add a number" drawer has a Priority select (No priority and the place's groups); the kiosk (WQ6) is to call `pg_waiting_join_input($post, $place, true, …)` the same way. The public site and QR page never ask it.
- At the desk each waiting row has a menu with the groups and "No priority" (`pg_waiting_set_priority()`, written only while the number waits; no open desk needed). `pg_waiting_priority_clean()` keeps only a key of the place's groups.

## How does a booking join? ("I have a booking")
- Offered where the place's Calling order names Bookings staff and the bookings are installed (`pg_waiting_takes_bookings()`): a switch on `queue.php` and "Has a booking today" in staff's drawer. The e-mail field then shows on the public page even with e-mail notices off.
- `pg_waiting_booking_find()`: bookings of those staff starting today in the site's zone (`pg_waiting_day_bounds()`), confirmed or requested, made with the phone (compared as `pg_sms_number()`) or the e-mail (no case); one already named by a number is passed; the nearest to now wins (`pg_waiting_booking_pick()`).
- The number gets `booking_id` and `booking_at`; the order rule stands it at the booked time within the grace window. The booking row is never written.
- A booking is held back only by the hours, not by the daily cap or the closing estimate. None found: refused with "No booking for today was found…" (code `booking`).
- The desk's Booked pill shows the booked time.

## What does "I'm on my way" do?
- Offered on the person's page for a number taken from the site (`source = 'site'`) while it waits, once (`pg_waiting_on_way_offered()`); it writes `on_way_at`, seen as the On the way pill at the desk. It does not change the order.

## Where does a moved-back number stand?
- "Not here" with "Move a person who is not here back once" on keeps `called_at` as the moment of the move; `pg_waiting_order_time()` stands the number at the later of its own time and that moment: behind everyone already waiting, before those who join later. Priority still counts.
- The person's page says "You were not here when called, so you wait again, once." (status field `note`).

## How was it checked?
- `tests/walk_in_queue_arrivals_test.php` (priority, the form, matching, pick, day bounds, on the way, moved back, wiring). No database in the cloud session: the booking lookup and the writes were not run against MySQL.

Related: docs/memory/commerce/walk-in-queue-rules.md, docs/memory/commerce/walk-in-queue-desk.md, docs/memory/commerce/walk-in-queue-number-page.md
