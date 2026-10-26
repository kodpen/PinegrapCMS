---
name: merger-wake
description: How the merging session keeps waking (2026-10-26): a 5-minute session cron, an hourly routine that re-creates it after a restart; GitHub events are not relied on
---

## Why not wait for GitHub events?
- A green CI result on a train did not reach the merging session twice (the night of 2026-10-25 and 2026-10-26 09:43): the subscription's event was lost when the container restarted. The train sat green, no PR landed, every worker waiting on its merge stopped, and the queue fell about five hours behind.
- Events and `send_later` check-ins still help, but nothing waits on them.

## What wakes the merging session?
- A session cron (`CronCreate`) every 5 minutes on off-minutes (`2,7,12,…,57 * * * *`). Each tick reads GitHub, not its own memory:
  - an open `merge-train/*` PR: green → check the PR heads and `origin/development` ancestry, land it by fast-forward ([[merge-train-landing]]), then build the next one; red → find the PR that breaks it; running → end quietly;
  - no open train: build one from the `ready`, green PRs (or green with no push for an hour) ([[merge-train]]);
  - nothing to do: no message.
- A session cron lives only while the session runs and dies with a restart. So an hourly routine (`create_trigger`, bound to the session, an off-minute such as `41 * * * *`) checks `CronList`, re-creates the tick when it is missing and runs one tick itself.

## What does a new merging session set up first?
- Both: the 5-minute tick and the hourly routine (delete an older routine of a session that is no longer merging). Then it lands or builds as the tick says. A recurring cron expires after 7 days; the hourly routine re-creates it.
- Reviews of a train run while its CI runs, not before the push: CI takes about ten minutes, a review by sub-agents about as long.

Related: docs/memory/workflow/merge-train.md, docs/memory/workflow/coordinator.md
