---
name: walk-in-queue-desk
description: Walk-in queue desk (WQ4, queue_desk.php): Today tab, My desk, place staff, Open this desk, Call next, Call again, Not here, Send to another service, Finish, Pause
module: walk_in_queue_desk
---

## Where is the desk?
- `queue_desk.php?place=<id>`: a place's Today tab for roles 0-2 (tabs Set-up, Desks, Today, Report), and "My desk" in the Calendars menu for a role-3 user on the place's staff list ("My desk · <place>" when several; drawn alone when the user has no calendar).
- Code: `includes/fn/walk_in_queue_desk.php`; pure parts in `tests/walk_in_queue_desk_test.php`. Step WQ4 of the walk-in-queue plan.

## Who reaches which place?
- `pg_waiting_desk_scope($role, $user_id, $places)`, shaped as `pg_booking_agenda_scope()`: null (every place) for roles 0-2, the ids of the places whose `settings.staff` names a role-3 user, false otherwise; `pg_waiting_desk_scope_allows()` checks a place.
- Staff are set on the place's Desks tab, card "Staff" (user picker, "Add staff", Remove per row), stored as user ids in `settings.staff` (`pg_waiting_place_staff_save()`).

## What does the screen show?
- No desk of the viewer's open here: "Open this desk", a row per desk (its services, Closed / "Open · person" / "Paused · person"), the button on closed rows. A user has one desk open per place; a desk opens only while closed (one conditional UPDATE), so two people never open one desk.
- A desk open: the now-serving card (number, service and desk, name, pills, "Called N min ago"). Buttons in one row: "Call next" (the fill), "Call again", "Not here", "Send to another service", "Finish" (quiet); Pause / Resume and "Close desk" in the card's menu.
- Under it "Waiting": today's waiting numbers for the desk's services (every service when no desk is open), in calling order: number, service, pills Priority, Booked (its time), On the way, minutes waited, a priority menu.
- The live part (`?part=live`) is read again every 15 seconds by `assets/js/queue_desk.js` while the tab is shown, no drawer or menu is open and no button in it has focus; a sign-in page as answer stops it.

## What does each button do?
- `pg_waiting_desk_buttons()` decides which work: Call next only at an open desk with nobody at it and someone waiting; the number at the desk is ended (Finish, Not here, sent on) before the next call or Close desk; Not here after the place's "Not here after" minutes.
- Call next (`pg_waiting_call_next()`): the first by `pg_waiting_next()` taken with `UPDATE … SET state = 'called' … WHERE id = ? AND state = 'waiting'`; when another desk took that person, the next is tried (5 tries). The desk's `GET_LOCK` (`pg_waiting_desk_lock_name()`, 5 s) is held from the look at the desk to the call, since two clicks once called two people to one desk (#11352). Then the "called" notice and `pg_waiting_notify_ahead()`.
- Call again: `called_at` moves to now (Not here counts from the last call; the lobby screen sees a new call). No second message.
- Not here: `no_show` with `done_at`; with the place's "Move a person who is not here back once" on, the first time it waits again with `moved_back = 1`, behind those waiting (`walk-in-queue-arrivals.md`).
- Send to another service: waits again in the other service, keeps `joined_at`, takes that service's letter and the day's next count (the unique key forbids keeping the old count); the person's page shows the new number at its next read.
- Finish: `served` with `done_at`; the estimate reads `done_at - called_at`. Each move is written only while the number is still `called` to that desk (`pg_waiting_ticket_move()`).
- Pause: `paused`, called to by nobody, not in the estimate; Close desk frees it (`user_id`, `opened_at` 0).

## How was it checked?
- On a sandbox (MariaDB, #11153): every button clicked, drawn at 1440 and 390 px; two desks at once and the notices not tried.

Related: docs/memory/commerce/walk-in-queue-places.md, docs/memory/commerce/walk-in-queue-rules.md, docs/memory/commerce/walk-in-queue-notices.md
