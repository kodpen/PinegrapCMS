---
name: coordinator-limits
description: Coordinator rules: model and effort of coordinators and workers, quota guard and scaling by the 5-hour window, 6 to 9 workers per coordinator, a new worker for a decided draft PR
---

## Why?
- At 21:37 on 2026-10-27 one account hit its 5-hour usage limit: a worker failed 15 seconds after its start and a running session lost its sub-agent. Two coordinators, their workers, the architect and the merging session shared that account's quota, each counting only its own workers.
- Workers waited hours for a decision (one 9.5 hours), paying their whole context on every check-in; a worker with an open decision question now ends its session (`session-lifecycle.md`), so the decision must reach a new session.

## Which quota guard?
- Before starting a worker the coordinator calls `get_session` on itself and reads `rate_limit_info` (status and `resetsAt`, no percentage). A session of its account that fails within a minute of its start is read as a limit hit: no new worker for 10 minutes, the owner told once.
- Each coordinator keeps at least 6 and at most 9 workers busy (owner's setting for the coordinators, 2026-11-02: the account has only a 5-hour window, no weekly limit; 4 to 8 before). Fewer than 6 only while the quota guard, the queue limit (CLAUDE.md, Working mode 8) or a lack of free steps stops a start.
- It scales by the 5-hour window: `allowed` → fill up to 9 while free work and queue places exist; a warning status → finishing workers are not replaced (down toward 6); limited → no new worker until `resetsAt`, then back up to 9.
- The queue rule (at most 6 of the 20 places per coordinator) stays.
- Starts are staggered: at most one new worker a minute. The architect starts no sub-agents for the same reason.

## Which model and effort?
- A coordinator runs on Opus 5.5 at xhigh effort with Ultracode on; a worker runs on Opus 5.5 at high effort (owner, 2026-11-02; until then coordinators ran at medium with Ultracode off). A coordinator starts every worker with `create_session` `model` `claude-opus-5-5`; the call takes no effort, and a session it starts opens at high (tried 2026-11-02), so nothing more is set. A coordinator's own xhigh and Ultracode are set by the owner when opening it.
- Every `create_session` (worker or new coordinator) passes `source_url` `https://github.com/kodpen/PinegrapCMS` and `source_revision` `development`, else the session has no repository.

## Which draft PR gets a new worker?
- A draft PR whose `Decision: #<issue>` issue is closed and whose session has ended: a new worker (`create_session`) for that branch, with the decision comment on the PR as the brief; the new session continues the branch, notes "decided in #<issue>" in the PR body and marks it ready when CI is green.
- The 10-minute check finds it by listing the drafts whose body has `Decision: #` and reading each issue's state; no polling of sessions.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/session-lifecycle.md, docs/memory/workflow/architect.md
