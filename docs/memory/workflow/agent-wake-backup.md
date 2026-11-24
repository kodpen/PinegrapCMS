---
name: agent-wake-backup
description: Every agent of the agent channel but the architect backs its request waiter with an hourly routine (at most 55 minutes apart) that restarts the waiter and, for a coordinator, refills its places
---

## Why is the waiter not enough?
- A background waiter ends at the two-hour limit of a background command, at a container restart and at its own timeout. A session whose waiter has ended wakes on nothing: it reads no request and replaces no finished worker.
- Seen 2026-11-24: both coordinators' waiters fell at the two-hour limit and were not started again; one kept only its 30-minute check-ins, so finished workers were not replaced for about three hours, and the design agent did not wake on a mention. The owner (agent channel message 12582): the waiter is backed by a scheduled wake, at worst every 55 minutes.

## What does each agent keep?
- The request waiter with a timeout under an hour (`tools/ws_wait.php --requests --timeout=3300`), started again in the same turn that reads its line, on exit 0 and on exit 3 (`agent-channel-request-wait.md`).
- One hourly routine bound to its own session (`create_trigger`, a 5-field cron on an off-minute such as `41 * * * *`, so two firings are never more than 55 to 60 minutes apart). It is made in the session's first turn and looked up with `list_triggers` after a restart; a second one is never made beside it.
- Who: the coordinators, the design agent, the test coordinator. The merging session has its own (`merger-wake.md`). The architect has none: its waiter alone wakes it (`architect-wake.md`).

## What does the routine's turn do?
- It looks whether a waiter runs; if none does, it reads the queue once (`php tools/ws_wait.php --requests --timeout=1`), handles what is queued and starts the waiter. It reports the model.
- A coordinator also counts its working workers against its 8 places and fills the free ones at once (`coordinator.md`); a ready PR and a draft without a worker hold no place.
- Nothing to do: no message to the channel, the turn ends.
- A coordinator's 30-minute check-in stays; each check-in makes the same look at the waiter.

## What must not happen?
- A session that stops its work without a word. An agent that ends, hands over or pauses says so in the channel first; the routine is deleted only then.
- A check-in that reads requests but leaves the waiter down.

Related: docs/memory/workflow/agent-channel-wake.md, docs/memory/workflow/merger-wake.md, docs/memory/workflow/coordinator.md
