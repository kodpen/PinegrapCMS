---
name: bug-hunt-areas-walk-in-queue
description: Bug hunt of the walk-in queue (WQ1-WQ9, area 6 named by the architect, 2026-11-24): six faults fixed in #11352, one decision question, what was read clean
---

## What was the area?
- The walk-in queue as closed out by WQ1-WQ9: `includes/fn/walk_in_queue*.php`, `queue.php`, `queue_desk.php`, `queue_kiosk.php`, `queue_lobby.php`, `edit_queue_place.php`, `assets/js/queue_page.js`, `queue_lobby.js`, the queue API routes, the `queue.served` survey touchpoint, erase and export. Area 6 of the architect's list, given by coordinator k1 (2026-11-24).

## What did it fix? (#11352 lists each with file and line)
- Two Call next requests at one desk (a double click, or the screen and the API) both found the desk free and called two people to it; one stayed called, hidden behind the other. The call now holds the desk's `GET_LOCK`.
- Remove on the Desks tab took a paused desk, stranding the number called to it. Only a Closed desk is removed (`pg_waiting_desk_removable()`).
- The person's page kept the old number after "Send to another service" until reloaded; the 15-second read fills it now.
- "Leave the queue" and "I'm on my way" stayed hidden once the number had been called, even when Not here moved it back to wait again.
- A double press of Take a number (or a resent form) took two numbers; the first was later called as not here. A second press within 60 seconds for the same service goes to the number just taken (page and kiosk).
- The people ahead of a new number counted every waiting number of the service, so a booking that came early (standing at its booked time) added one: in the "taken" notice, the "ahead" bit and the closing refusal.

## What is left for a decision?
- Only the desk's own user can close or pause it; a manager has no way to close a desk a staff member left open (it stays in the estimate, holds its called number, and Remove refuses it). The same for a number left called at closing: it stays at the desk the next morning. Asked under `## Decision question` in #11352.

## What was read clean?
- Two desks calling at once: the conditional UPDATE on `state = 'waiting'` and the 5 tries.
- Not here: moved back once (`moved_back`, standing at the move), then `no_show`; each move only while still called to that desk.
- Day roll-over: the day's count is `MAX(number) + 1` per place, day and service, the cap counts the day's numbers; an earlier day's waiting number reads as left on its page and is not on the desk, the lobby or the kiosk.
- The lobby read (5 s, no session, hidden tab skipped, stamp chimes on a new call or Call again); the estimate with one desk open (paused and closed desks do not count, none gives "N ahead" only).
- `queue.served` comes only from Finish's conditional move, so once; the survey row is not queued twice for one person and survey, and is cancelled when the keep period cleared the number.
- Rights: places, report and poster for roles 0-2; the desk and the API by `pg_waiting_desk_scope()`; a priority change only on a waiting number of the place.
- Erase sets a waiting or called number to left, then empties the person's fields and the link hash; export leaves out the hash and the staff columns.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/walk-in-overview.md
