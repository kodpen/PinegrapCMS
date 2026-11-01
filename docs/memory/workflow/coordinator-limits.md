---
name: coordinator-limits
description: Coordinator rules (2026-10-27): quota guard before a worker starts, 4 to 8 workers per coordinator, a new worker for a draft PR whose decision came after its session ended
---

## Why?
- At 21:37 on 2026-10-27 one account hit its 5-hour usage limit: a worker failed 15 seconds after its start and a running session lost its sub-agent. Two coordinators, their workers, the architect and the merging session shared that account's quota, each counting only its own workers.
- Workers waited hours for a decision (one 9.5 hours), paying their whole context on every check-in; a worker with an open decision question now ends its session (`session-lifecycle.md`), so the decision must reach a new session.

## Which quota guard?
- Before starting a worker the coordinator calls `get_session` on itself and reads `rate_limit_info`: a status other than `allowed` means no new worker until `resetsAt`. A session of its account that fails within a minute of its start is read as a limit hit: no new worker for 10 minutes, the owner told once.
- Each coordinator keeps at least 4 and at most 8 workers busy (since 2026-11-01; 3 to 6 from 2026-10-31, before that 6 per account shared by its coordinators). Fewer than 4 only while the quota guard, the queue limit (CLAUDE.md, Working mode 8) or a lack of free steps stops a start. The queue rule (at most 6 of the 20 places per coordinator; 4 of 15 before 2026-10-28) stays.
- Starts are staggered: at most one new worker a minute. The architect starts no sub-agents for the same reason.

## Which draft PR gets a new worker?
- A draft PR whose `Decision: #<issue>` issue is closed and whose session has ended: a new worker (`create_session`) for that branch, with the decision comment on the PR as the brief; the new session continues the branch, notes "decided in #<issue>" in the PR body and marks it ready when CI is green.
- The 10-minute check finds it by listing the drafts whose body has `Decision: #` and reading each issue's state; no polling of sessions.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/session-lifecycle.md, docs/memory/workflow/architect.md
