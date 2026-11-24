---
name: walk-in-queue-decisions
description: Walk-in queue decisions: owner polls 201 and 207, the architect's tables, place, roles and screen parts, the words on screen, what binds the code, what was left out
---

## Who decided the queue, and what?
- Owner (2026-11-20): yes to the whole queue, poll 201 (`workflow/decisions-made-product-93.md`). Notices are service messages, no marketing consent or İYS, no promotion in them, poll 207 (`workflow/decisions-made-product-94.md`).
- Owner (2026-11-20): tables `waiting_places`, `waiting_desks`, `waiting_tickets`, poll 206 (`workflow/decisions-made-schema-46.md`); columns and personal data in `decisions-made-schema-47.md`.
- Architect (2026-11-20): no "queue" in a table name, which reads as a job queue beside `push_queue`. Services with letters, hours, cap and notices are JSON on the place; a ticket names the service's key, its desk and a booking id; no column on an existing table (agent channel message 11145).
- Architect (2026-11-20): the place is Calendars › Queue beside Bookings; roles 0-2, and a role-3 user only as a place's staff through `pg_waiting_desk_scope()`, shaped as `pg_booking_agenda_scope()` (message 11145).
- Architect (2026-11-20): the parts missing from the Design examples screen: the desk's now-calling block is one `.pg-card` with `.pg-figure-value`; the lobby's figure cards; the kiosk's services as the New application sheet's radio cards (message 11146).

## What binds the code?
- "Call next" and every move of a number is one conditional UPDATE on its state: two desks never call the same person (`walk-in-queue-desk.md`).
- The person's page and the lobby show the number, never a name; the signed link opens that number only; `queue.php` is in `waf_sensitive_scripts()`.
- A booking keeps its rows and moves: an arrival is a queue row naming the booking (`walk-in-queue-arrivals.md`).
- A notice goes only for the number's own moves, at most three, to the phone given for it (`walk-in-queue-notices.md`).
- The estimate reads "about" and shows from 20 handled (`walk-in-queue-rules.md`); priority groups are the place's setting, the reason seen at the desk.
- Times unix UTC, days in the site's zone, hours in the bookings' shape (`pg_booking_hours_clean()`).

## Which words are on screen?
- "Place", "Desk", "Number", "Waiting", "Now serving", "Not here", "about"; never "ticket", "token" or a column's name.
- The places list, a place's head and the desk have one fill each; Set-up has six folded rows, one open at a time, a row missing values carries "Not set" (`walk-in-queue-places.md`).

## What was left out?
- Paid priority, paper tickets from a printer, numbers read aloud, several sites' queues in one place, AI staffing forecasts: each needs a new yes from the owner.

Related: docs/memory/commerce/walk-in-queue.md, docs/memory/commerce/walk-in-overview.md
