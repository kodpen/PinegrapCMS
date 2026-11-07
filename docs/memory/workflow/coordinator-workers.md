---
name: coordinator-workers
description: How many workers a coordinator keeps (since 2026-11-07): 8 working, a session waiting on someone else does not count, when fewer; no count from the quota
---

## Why a fixed 8?
- From 2026-10-31 to 2026-11-07 the count followed the 5-hour quota (3 to 6, 4 to 8, then 4 to 10 retuned each hour from the sessions' cost). It was misread: on 2026-11-07 a coordinator counted the 8 sessions it took over as full, two of them waiting since 22:39 the day before for the PR owner's review, so 6 worked and none was started.
- A fixed number needs nothing read from the quota each hour, and the two coordinators running then fit the queue limit: 2 × 8 of the 25 places.

## How many?
- Each coordinator keeps 8 workers working. Nothing is read from the quota to raise or lower it.

## What counts as a worker?
- A session working on its step: from its start to its PR's merge, a fix the merging session asked for included.
- Not counted: a session that waits on someone else and produces nothing, such as the owner's review, a decision (`session-lifecycle.md`) or its PR's merge with no next step. Its place gets a new worker on a free step.

## When fewer than 8?
- Only while the queue limit (CLAUDE.md, Working mode 8: a coordinator's workers hold at most 8 of the 25 places), a lack of free steps or the limit guard (`coordinator-limits.md`) stops a start.
- Running workers are never stopped to keep the count; starts are staggered, at most one a minute.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/coordinator-limits.md
