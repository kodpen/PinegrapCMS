---
name: walk-in-queue-steps
description: Plan walk-in-queue, steps WQ1a to WQ9: the pure rules, the store, places and the poster, the public page and notices, the desk, priority and bookings, lobby and kiosk, report, API, close-out
---

## Which steps are there?
- WQ1a (S) first, no schema, done in #10924: new `includes/fn/walk_in_queue.php`, pure.
  - Its rules: the order rule (priority, a booking within the grace window), the number's letter and count, the cap and closing rule, the estimate (median of the handled, open desks, rounding), the not-here timer, the signed token. `tests/walk_in_queue_test.php` red first: two desks and one service, a priority person, a booking 10 minutes late, 19 and 20 handled, a number after the closing estimate. Features: W3, W8, W9 (the rules).

- WQ1b (M) after WQ1a, done in #10956: `waiting_places`, `waiting_desks`, `waiting_tickets` (poll 206, `workflow/decisions-made-schema-46.md`) as a migration step in the open version, their columns approved by the architect (`workflow/decisions-made-schema-47.md`); the five privacy registrations and `pg_contact_holders()`; the keep-period clear in the general job; their tests. Features: W14.

- WQ2 (M) after WQ1b, done in #11038: the places list and a place's screen (services, hours, desks, notices, order, keep period), the printable QR poster (`commerce/walk-in-queue-places.md`; rows read "Messages" and "Calling order"). Features: W1, W2.

- WQ3 (M) after WQ2, done in #11108: the public `queue.php` (take a number, the person's page with its 15-second read, Leave the queue) and its `waf_sensitive_scripts()` entry; staff adding a number; kind `queue` in `pg_sms_kinds()` and the WhatsApp template, a service message (poll 207), never asking marketing consent; the notices. Features: W3, W5, W6.

- WQ3b (S) after WQ3, done in #11147: the person's 15-second read leaves the sensitive allowance for a bucket of its own, 240 a minute per address, a 429 with no offence past it; no session for the read; the lobby's read of WQ6 joins it (`waf/waf-queue-status-bucket.md`).

- WQ4 (M) after WQ3, done in #11153: the desk screen: open and close a desk, Call next, Call again, Not here, Send to another service, Finish, Pause. Features: W2, W7.

- WQ5 (M) after WQ4, done in #11219: priority at the kiosk and the desk; bookings folded in ("I have a booking" finds today's booking with the place's staff); remote join, "I'm on my way", moved back once. Features: W4, W8.

- WQ6 (S) after WQ4, done in #11223: the lobby screen (a signed display link, no menu, the chime) and the kiosk screen. Features: W10.

- WQ7 (M) after WQ5, done in #11246: the report with the staffing line, Excel. Features: W11.

- WQ8 (S) after WQ7, done in #11276: the four events through `pg_announce()`, their catalogues and payload fixtures (`workspace/workspace-flows-adding.md`); the API routes with their types, `php tools/check_api_schema.php`; the MCP tools. Features: W12, W13 (`commerce/walk-in-queue-events.md`, `api/api-walk-in-queue-routes.md`).

- WQ8b (S) after WQ8, done in #11293: `queue.served` as a satisfaction-survey touchpoint (`platform/feedback-sends.md`): its line in `pg_feedback_event_record()`'s map and the survey's touchpoint choice. Features: W12.

- WQ9 (S) close-out: every W number checked against the code with its PR; `commerce/walk-in-queue.md` written; this plan's files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- WQ1a can start before the architect's answer. After WQ4: WQ5 and WQ6. WQ3 enters `waf.php` and `includes/fn/sms.php`, WQ8 the webhook catalogue: check the open PRs before claiming.

Related: docs/memory/plans/walk-in-queue.md, docs/memory/plans/walk-in-queue-features.md, docs/memory/plans/walk-in-queue-screens.md
