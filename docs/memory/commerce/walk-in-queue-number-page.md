---
name: walk-in-queue-number-page
description: The walk-in queue's public queue.php (WQ3): taking a number, the person's page and its 15-second read, Leave the queue, staff's Add a number, the limit, the firewall entry
module: walk_in_queue_tickets
---

## Where is a number taken?
- `queue.php?place=<id>[&from=qr]` (root, in the secure shell `output_header_secure()` + `pg_secure_card()`, as `feedback.php` and `booking_manage.php`). The QR poster links with `from=qr` (way `qr`), the site with nothing (way `site`); each needs its switch in "Taking a number" and the place `open` (`pg_waiting_way_open()`), else the closed text (or the standard one) shows.
- The form: services as radio cards (`form-field form-field-card`, the letter in `.pg-icon-tile-lg`), party size when asked (1-50), name, phone, e-mail (only while the place's e-mail notice is on), all optional; "Take a number" is the one fill. Read by `pg_waiting_join_input()` (phone through `pg_sms_number()`).
- Staff: "Add a number" (ghost, toolbar of the place's screen, both tabs; disabled while the queue is not open) opens a drawer: service, party size, name, phone, e-mail. Way `staff`: any switch, same hours, cap and closing rules.

## What does taking a number do?
- `pg_waiting_take()`: `pg_waiting_join_refusal()` with the day's count, the cap, the estimate of the new number and `pg_waiting_closes_at()`; the count is the service's `MAX(number) + 1`, a clash on the unique key retries (5 tries). The token (`pg_waiting_ticket_token()` of id and joined_at) is made after the insert and only its sha256 stored.
- A browser keeps its number per place in the session; the place page then shows "You have number A-012" with "See your turn", so going back does not take a second one by mistake.
- Limit: 10 numbers per address in 10 minutes (`pg_waiting_throttled()`, `waf_rate_exceeded()` key `queue_take`, logged as `rate-queue-take`).

## What does the person's page show?
- `queue.php?n=<id>&t=<token>`: the row is read by id and the token compared (`pg_waiting_ticket_find()`); `token_hash` keeps no index. A cleared hash opens nothing.
- The place's name, the number large (`pg-display-4`), then `pg_waiting_status_view()`: "N ahead of you" / "You are next" and "About M minutes" (only with an estimate), "It is your turn" with the desk, or the final words for served, left, no_show. A waiting number of an earlier day reads as left. Numbers only, never a name.
- `&read=1` answers the same in JSON; `assets/js/queue_page.js` reads it every 15 seconds while the tab is shown and stops on a final state. "Leave the queue" (quiet, asks first) posts with the form token; `pg_waiting_leave()` sets `left` only from `waiting`.

## Which notices go?
- Taken, ahead and called, at most three: `walk-in-queue-notices.md`.

## What does the firewall do with it?
- `queue.php` is in `waf_sensitive_scripts()` (30 a minute per address, open-request cap) for opening the page, taking a number and "Leave the queue". The 15-second read (`&read=1`) has a bucket of its own, 240 a minute per address, a 429 past it, and starts no session: `waf/waf-queue-status-bucket.md` (WQ3b).

## How was it checked?
- `tests/walk_in_queue_tickets_test.php` (join form, ways, texts, channels, bits, status view, link, wiring). No database in the cloud session: take, leave, the read and the notices were not run against MySQL.

Related: docs/memory/commerce/walk-in-queue-notices.md, docs/memory/commerce/walk-in-queue-rules.md, docs/memory/commerce/walk-in-queue-places.md, docs/memory/commerce/walk-in-queue-store.md
