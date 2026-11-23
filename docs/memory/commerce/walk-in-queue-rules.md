---
name: walk-in-queue-rules
description: The walk-in queue's pure rules in includes/fn/walk_in_queue.php: the order, the number A-012, cap and closing, the estimate, Not here, the signed link token
module: walk_in_queue
---

## Where are the queue's rules?
- `includes/fn/walk_in_queue.php`, pure: no table read, plain arrays in, so the store, the desk, the person's page and the API share one answer. Tests: `tests/walk_in_queue_test.php`. Built in #10924 (step WQ1a of the walk-in-queue plan).

## In what order are people called?
- `pg_waiting_order($tickets, $grace_seconds)`: priority people first (a non-empty `priority` reason), then by `pg_waiting_order_time()`, then by id.
- A walk-in stands at `joined_at`; a moved-back number at the later of that and its `called_at`. A booking (`booking_at` > 0) that arrived by its time plus the grace window stands at its booked time, never earlier when it came early; a later one stands at its arrival as a walk-in.
- `pg_waiting_next($tickets, $services, $grace)`: the first in that order among the desk's services. `pg_waiting_ahead()` counts only the same service.

## How is a number written?
- `pg_waiting_number($letter, $count)`: one Latin capital (`pg_waiting_letter_clean()`) and the day's count padded to three, `A-012`; past 999 it keeps going (`A-1000`). `pg_waiting_next_count($last)` is the last plus one.

## When is a new number refused?
- `pg_waiting_join_refusal()` answers `closed` (outside the hours or at the closing moment), `cap` (the daily cap reached; 0 is no cap), `closing` (now plus the estimate passes the closing time), or `''`. Without an estimate only the hours count.
- `pg_waiting_closes_at($hours, $exceptions, $now, $zone)`: the end of the open range now is in, 0 when closed; hours and dated exceptions in the bookings' shapes, a dated empty list is a day off.

## How is the wait worked out?
- `pg_waiting_handling_seconds($durations, $start_minutes)`: the median from 20 handled numbers (`PG_WAITING_MEDIAN_FROM`); before that the place's minutes for the service; null when neither, and then only "N ahead" is shown. The caller passes the last 14 days' durations.
- `pg_waiting_estimate_seconds($ahead, $handling, $open_desks)`: ahead times handling over the open desks serving the service (`pg_waiting_open_desks()`, state `open` only; paused and closed desks do not count); null with no open desk.
- `pg_waiting_estimate_minutes()`: to the nearest 5 minutes, never below 5 while there is a wait.

## What does Not here do?
- `pg_waiting_not_here_allowed($called_at, $now, $minutes)`: offered M minutes after the call. `pg_waiting_not_here_outcome($moved_back, $move_back)`: `moved_back` the first time when the place's setting is on (off by default), else `left`. A moved-back number keeps `called_at` as the moment of the move and stands there (`walk-in-queue-arrivals.md`).

## How is a number's link signed?
- `pg_waiting_ticket_token($id, $joined_at, $key)`: HMAC-SHA256 of `waiting-ticket:<id>:<joined_at>` under `ENCRYPTION_KEY` (`pg_booking_key()`), the booking manage link's way; only its sha256 is stored and `pg_waiting_ticket_token_valid()` compares with `hash_equals()`. No new secret. A cleared hash opens nothing.
- A changed `ENCRYPTION_KEY` breaks the links already sent, as for a booking's manage link.

Related: docs/memory/plans/walk-in-queue.md, docs/memory/commerce/bookings-model.md
