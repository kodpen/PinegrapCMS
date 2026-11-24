---
name: walk-in-overview
description: Start file for the walk-in-queue-* files: the walk-in queue (Calendars › Queue), its decisions, its screens by label, which file holds each part, adding one
module: walk_in_queue
---

## What is it?
- A walk-in queue for offices and shops: a visitor takes a number (QR poster, the site, a kiosk, staff), follows it on their own page and is told by SMS, WhatsApp or e-mail; staff call the next at a desk; a lobby screen shows the calls; a report gives waits and staffing.
- Built by the walk-in-queue plan (`plans/walk-in-queue*.md`) in 2026.11.0, steps WQ1a to WQ8.

## Which decisions bind it?
- Into the plan (owner, poll 201): `plans/walk-in-queue.md`. Tables: `workflow/decisions-made-schema-46.md`; columns and personal data: `decisions-made-schema-47.md`. Notices as service messages (poll 207): `decisions-made-product-94.md`.

## Which file holds what?
- Pure rules (order, number, cap, estimate, token): `walk-in-queue-rules.md`. Tables, keep-period clear, privacy: `walk-in-queue-store.md`.
- Places list, Set-up and Desks tabs, the QR poster: `walk-in-queue-places.md`. The public `queue.php` and the person's page: `walk-in-queue-number-page.md`; its notices: `walk-in-queue-notices.md`.
- The desk screen (Call next, Finish, Not here, ...): `walk-in-queue-desk.md`. Priority, bookings, "I'm on my way": `walk-in-queue-arrivals.md`. Lobby and kiosk screens: `walk-in-queue-lobby-kiosk.md`. Report: `walk-in-queue-report.md`.
- Events: `walk-in-queue-events.md`; API routes and MCP tools: `api/api-walk-in-queue-routes.md`. Firewall bucket of the 15-second read: `waf/waf-queue-status-bucket.md`.

## How is a new part added?
- Functions in an `includes/fn/walk_in_queue_<part>.php` module loaded by `functions.php`, behind `pg_waiting_ready()`; a move of a number is one conditional UPDATE on its state; a new event goes through every catalogue in `walk-in-queue-events.md`.

Related: docs/memory/plans/walk-in-queue-steps.md
