---
name: merger-wake
description: How the merging session keeps waking: a send_later chain, 3 minutes with more than 3 ready PRs and 15 minutes otherwise (2026-11-09), an hourly routine that re-arms it; GitHub events are not relied on
---

## Why not wait for GitHub events?
- A green CI result on a train did not reach the merging session twice (the night of 2026-10-25 and 2026-10-26 09:43): the subscription's event was lost when the container restarted. The train sat green, no PR landed, every worker waiting on its merge stopped, and the queue fell about five hours behind.
- Events and `send_later` check-ins still help, but nothing waits on them.

## What wakes the merging session?
- A server-side `send_later` chain, named `Merger: tick`: each firing arms the next first, then ticks. The interval follows the queue (owner, 2026-11-09): 3 minutes while more than 3 open PRs carry the `ready` label (development is busy), 15 minutes while 3 or fewer do (development has slowed and a tick every 3 minutes mostly finds nothing). A tick reads GitHub, not its own memory:
  - an open `merge-train/*` PR: green → check the PR heads and `origin/development` ancestry, land it by fast-forward ([[merge-train-landing]]), then build the next one; red → find the PR that breaks it; running → end quietly;
  - no open train: build one from the `ready`, green PRs (or green with no push for an hour) ([[merge-train]]);
  - nothing to do: no message.
- An hourly routine (`create_trigger`, bound to the session, an off-minute such as `41 * * * *`) re-arms the chain when no `Merger: tick` is pending and runs one tick itself.
- The lander that waits for a train's CI runs in the background and wakes the session when it ends, so a train lands without waiting for a tick.
- No session cron: since 2026-11-03 the accounts' quota is not unlimited, and every wake costs a turn. A 5-minute cron ran until then (a cron dies with a restart; the chain is server-side).
- The chain moved to 3 minutes later on 2026-11-03 (10 minutes before that): ten minutes between a green train and the next build let the queue grow past its limit. Since 2026-11-09 the 3 minutes hold only for a busy queue (above); a green train still lands at once through the lander.
- The same day the architect and the coordinators moved to a 30-minute chain (`architect.md`, `coordinator.md`): every wake of a standing session pays for its whole context.

## What does a new merging session set up first?
- The tick chain and the hourly routine (delete an older routine or chain of a session that is no longer merging). Then it lands or builds as the tick says.
- A fresh merging session per window (a routine that creates a session on each firing) is equally safe: the open `merge-train/*` PR is the lock.
- Reviews of a train run while its CI runs, not before the push: CI takes about ten minutes, a review by sub-agents about as long.

## Is the next train built before the open one lands?
- Yes (2026-10-26): on the open train's tip, pushed and opened at once, so both CIs run together. The first lands by fast-forward, after which the second's base is `development` itself; its PR then shows only its own merges. A red first train means rebuilding the second on the rebuilt first.
- The old rule against stacked trains came from squash landings, which rewrote the first train's commits and left every train built on it `dirty`.

Related: docs/memory/workflow/merge-train.md, docs/memory/workflow/coordinator.md
