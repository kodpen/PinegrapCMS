---
name: walk-in-queue-features
description: Plan walk-in-queue, features W1 to W14: places and desks, taking a number, remote join, the person's page, notices, calling, the order rule, the estimate, the lobby screen, report
---

## Where does a queue live?
- (W1) A queue place: name, address, the services it serves (each with a letter for its numbers, A-012), its hours in the bookings' hours shape, a daily cap, the text shown when it is closed.
- (W2) Desks of a place: name and the services each serves. A staff member opens a desk for their shift and closes it; only an open desk is called to and counted in the estimate.

## How does a person join?
- (W3) A number is taken from the place's QR poster, the place's public page, a kiosk screen on a tablet, or by staff (name and phone optional). A restaurant's place asks the party size. No new number when the estimate passes the closing time or the daily cap is reached.
- (W4) Remote join from the site within the hours, with "I'm on my way". A person called and not there is moved back once (a setting, off by default); the second time they leave.
- (W5) The person's page by a signed link: their number, how many are ahead, the estimate, the desk when called, "Leave the queue". It refreshes itself every 15 seconds.

## How is a person told?
- (W6) SMS or WhatsApp when the number is taken (with the link), when N are ahead (N a setting, 3 by default) and on the call with the desk's name; e-mail when only an e-mail is given. At most three messages a number.

## How is the next person called?
- (W7) "Call next", "Call again", "Not here" (after M minutes, a setting), "Send to another service" (keeps the time joined), "Finish", "Pause".
- (W8) The order: oldest first within a service; a priority person (elderly, disabled, pregnant, a setting per place) goes first; a booking at the place checked in goes in at its time within a grace window, after it as a walk-in.

## What does the person see as a wait?
- (W9) Each service's median handling time of the last 14 days (from 20 served; before that the length the place gives), divided by the open desks that serve it; shown rounded to 5 minutes as "about". Without enough data only "N ahead".

## What else?
- (W10) The lobby screen: a page with no menu for a TV or monitor, now-serving numbers and desks, a chime on each call; numbers only, never a name.
- (W11) The report: wait and handling time (median and the slowest tenth), people who left, not here, per desk and staff member, arrivals by hour and weekday, and the desks that would have kept the wait under a target; Excel (`pg_xlsx_write_book()`).
- (W12) Events `queue.joined`, `queue.called`, `queue.served`, `queue.no_show` in the webhook and flow catalogues (`workspace/workspace-flows-adding.md`); `queue.served` is a touchpoint of the survey plan (`plans/feedback-loop.md`) once both are built.
- (W13) `GET /queue/places`, `GET /queue/tickets`, `POST /queue/tickets` (a kiosk application joins), the call and finish routes; the MCP tools.
- (W14) A number's name, phone and e-mail are cleared after the place's keep period (30 days by default); the figures stay. Export and erase cover them.

Related: docs/memory/plans/walk-in-queue.md, docs/memory/plans/walk-in-queue-steps.md
