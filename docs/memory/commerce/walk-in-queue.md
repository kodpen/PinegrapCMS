---
name: walk-in-queue
description: Walk-in queue features W1 to W14 (Calendars › Queue, queue.php, the desk, lobby, kiosk, report, events, API): what each does, the file that holds it and the PR that built it
module: walk_in_queue
---

## What was built, and where? (plan walk-in-queue, 2026.11.0)
- Start file: `walk-in-overview.md`. Decisions and what was left out: `walk-in-queue-decisions.md`. Each line names the feature, its memory file and the PRs.

## Where does a queue live? (W1, W2)
- W1, a place: name, address, services with a letter each (A-012), hours in the bookings' shape, daily cap, the closed text; JSON on `waiting_places`. #11038, `walk-in-queue-places.md`.
- W2, desks: name and services; a staff member opens and closes one, only an open desk is called to and counted (#11038 the Desks tab, #11153 Open this desk and Close desk, `walk-in-queue-desk.md`).

## How does a person join? (W3 to W5)
- W3, a number from the QR poster, the site page, the kiosk or staff (name and phone optional), party size by a switch; none past the cap or the closing estimate. Rules #10924 (`walk-in-queue-rules.md`), `queue.php` #11108 (`walk-in-queue-number-page.md`), kiosk #11223 (`walk-in-queue-lobby-kiosk.md`).
- W4, remote join from the site within the hours, "I'm on my way", moved back once (off by default). #11219, `walk-in-queue-arrivals.md`.
- W5, the person's page by a signed link, read every 15 seconds, "Leave the queue". #11108; its firewall bucket #11147 (`waf/waf-queue-status-bucket.md`).

## How is a person told? (W6)
- W6, SMS, WhatsApp or e-mail when taken, when N are ahead (3 by default) and on the call; at most three, kind `queue` as a service message. #11108, `walk-in-queue-notices.md`.

## How is the next person called? (W7, W8)
- W7, "Call next", "Call again", "Not here", "Send to another service", "Finish", "Pause". #11153, `walk-in-queue-desk.md`.
- W8, the order: oldest first per service, priority first, a booking at its time within the grace window. Rule #10924; priority and "I have a booking" #11219 (`walk-in-queue-arrivals.md`).

## What does the person see as a wait? (W9)
- W9, the median handling time of 14 days from 20 handled, divided by the open desks, rounded to 5 minutes as "about"; otherwise "N ahead". Rule #10924 (`walk-in-queue-rules.md`), its read #11108.

## What else? (W10 to W14)
- W10, the lobby screen: signed display link, no menu, now serving, five past calls, the chime; numbers only. #11223, `walk-in-queue-lobby-kiosk.md`.
- W11, the Report tab: waits and handling, left and not here per desk, staff and service, arrivals by hour, Desks needed, Excel. #11246, `walk-in-queue-report.md`.
- W12, events `queue.joined`, `queue.called`, `queue.served`, `queue.no_show` (#11276, `walk-in-queue-events.md`); `queue.served` a survey touchpoint (#11293, `platform/feedback-sends.md`).
- W13, `GET /queue/places`, `GET` and `POST /queue/tickets`, the call and finish routes, the MCP tools. #11276, `api/api-walk-in-queue-routes.md`.
- W14, name, phone and e-mail cleared after the keep period (30 days by default), export and erase. #10956, `walk-in-queue-store.md`.

Related: docs/memory/commerce/walk-in-overview.md, docs/memory/commerce/walk-in-queue-decisions.md
