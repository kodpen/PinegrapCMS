---
name: merger-wake
description: How the merging session keeps waking: a 10-minute send_later chain and an hourly routine that re-arms it (2026-11-03); GitHub events are not relied on
---

## Why not wait for GitHub events?
- A green CI result on a train did not reach the merging session twice (the night of 2026-10-25 and 2026-10-26 09:43): the subscription's event was lost when the container restarted. The train sat green, no PR landed, every worker waiting on its merge stopped, and the queue fell about five hours behind.
- Events and `send_later` check-ins still help, but nothing waits on them.

## What wakes the merging session?
- A server-side `send_later` chain every 10 minutes, named `Merger: 10-minute tick`: each firing arms the next first, then ticks. A tick reads GitHub, not its own memory:
  - an open `merge-train/*` PR: green → check the PR heads and `origin/development` ancestry, land it by fast-forward ([[merge-train-landing]]), then build the next one; red → find the PR that breaks it; running → end quietly;
  - no open train: build one from the `ready`, green PRs (or green with no push for an hour) ([[merge-train]]);
  - nothing to do: no message.
- An hourly routine (`create_trigger`, bound to the session, an off-minute such as `41 * * * *`) re-arms the chain when no `Merger: 10-minute tick` is pending and runs one tick itself.
- The lander that waits for a train's CI runs in the background and wakes the session when it ends, so a train lands without waiting for a tick.
- No session cron: since 2026-11-03 the accounts' quota is not unlimited, and every wake costs a turn. A 5-minute cron ran until then (a cron dies with a restart; the chain is server-side).
- The same day the architect and the coordinators moved to a 30-minute chain (`architect.md`, `coordinator.md`): every wake of a standing session pays for its whole context.

## What does a new merging session set up first?
- The 10-minute chain and the hourly routine (delete an older routine or chain of a session that is no longer merging). Then it lands or builds as the tick says.
- A fresh merging session per window (a routine that creates a session on each firing) is equally safe: the open `merge-train/*` PR is the lock.
- Reviews of a train run while its CI runs, not before the push: CI takes about ten minutes, a review by sub-agents about as long.

## Is the next train built before the open one lands?
- Yes (2026-10-26): on the open train's tip, pushed and opened at once, so both CIs run together. The first lands by fast-forward, after which the second's base is `development` itself; its PR then shows only its own merges. A red first train means rebuilding the second on the rebuilt first.
- The old rule against stacked trains came from squash landings, which rewrote the first train's commits and left every train built on it `dirty`.

Related: docs/memory/workflow/merge-train.md, docs/memory/workflow/coordinator.md
