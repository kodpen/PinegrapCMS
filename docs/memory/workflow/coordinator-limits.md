---
name: coordinator-limits
description: Coordinator rules: model and effort, the limit guard, a new worker for a decided draft, no hand-over at depth 7, archiving done workers and old coordinators
---

## Why?
- At 21:37 on 2026-10-27 one account hit its 5-hour usage limit: a worker failed 15 seconds after its start and a running session lost its sub-agent. Two coordinators, their workers, the architect and the merging session shared that account's quota, each counting only its own workers.
- Workers waited hours for a decision (one 9.5 hours), paying their whole context on every check-in; a worker with an open decision question now ends its session (`session-lifecycle.md`), so the decision must reach a new session.

## Which limit guard?
- Before starting a worker the coordinator calls `get_session` on itself and reads `rate_limit_info`: a refused status (`rejected`) means no new worker until `resetsAt`, since a session started then fails. A warning (`allowed_warning`) changes nothing. A session of its account that fails within a minute of its start is read as a limit hit: no new worker for 10 minutes, the owner told once.
- How many workers: `coordinator-workers.md`. Starts are staggered: at most one new worker a minute. The architect starts no sub-agents.

## Which model and effort?
- A coordinator runs on Opus 5.5 at xhigh effort with Ultracode on; a worker runs on Opus 5.5 at high effort (owner, 2026-11-02; until then coordinators ran at medium with Ultracode off). A coordinator starts every worker with `create_session` `model` `claude-opus-5-5`; the call takes no effort, and a session it starts opens at high (tried 2026-11-02), so nothing more is set. A coordinator's own xhigh and Ultracode are set by the owner when opening it.
- Every `create_session` (worker or new coordinator) passes `source_url` `https://github.com/kodpen/PinegrapCMS` and `source_revision` `development`, else the session has no repository.

## Which draft PR gets a new worker?
- A draft PR whose `Decision: #<issue>` issue is closed and whose session has ended: a new worker (`create_session`) for that branch, with the decision comment on the PR as the brief; the new session continues the branch, notes "decided in #<issue>" in the PR body and marks it ready when CI is green.
- The 30-minute check finds it by listing the drafts whose body has `Decision: #` and reading each issue's state; no polling of sessions.

## What happens at session depth 8?
- A session started by another session is one step deeper in the lineage (`get_session`, `lineage.depth`, limit 8). At depth 8 it can start no session and set no schedule (`create_session`, `send_later` and `create_trigger` are refused), and no setting raises the limit: the eighteenth hand-over by `create_session` reached it on 2026-11-02 (#5412). Below 8 nothing is limited. A coordinator hands over with `create_session` while its successor can still start workers, so up to depth 6 (the successor is born at 7). At depth 7 it does not hand over and asks no one for one: it keeps its workers and check-ins and works on, whatever its context size.

## Which sessions does it archive?
- At each check-in, every idle worker whose work is done, its own or handed over to it: its PR merged or closed and no next draft (`archive_session`). A worker whose PR still waits for its merge is kept.
- After a hand-over, the new coordinator archives its predecessor at once, and any older coordinator of the account still open (`list_sessions` `mine`).

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/session-lifecycle.md, docs/memory/workflow/architect.md
