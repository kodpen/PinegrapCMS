---
name: coordinator-quota
description: Coordinator worker count by the 5-hour quota (owner, 2026-11-02): 8 at a window's start, 4 to 10, retuned each hour against 20% a hour, read from the sessions' cost
---

## Why?
- The coordinators' accounts have only the 5-hour limit, no weekly one: a window not spent is lost, a window spent too fast stops every session of the account until it resets. The aim is to end each window near 100% without reaching the limit.

## What does the coordinator read?
- `get_session` on itself: `rate_limit_info.status` and `resetsAt` (the window's end; its start is `resetsAt` − 5 hours). It gives no percentage.
- Use U: the sum of `usage.cost_usd` of the account's sessions (`list_sessions mine`, `get_session` each: itself, its workers, the architect and merging session when on the account) spent in this window, over the window budget B. At the window's start it notes each running session's `cost_usd`; a session's spend is its cost now minus that note (all of it for a session started later).
- B is measured, not guessed: the first time the status leaves `allowed`, the window's spend then is B. Until B is known the coordinator keeps 8. B and the start notes go into its handover note.

## How many workers?
- At a window's start: 8.
- Each hour of the window (from the hourly routine), U against the target H = hours since the window's start (a fraction is fine) × 20%: U above H+10: two fewer; H+5 to H+10: one fewer; within ±5: same; H−10 to H−5: one more; below H−10: two more. Never under 4 or over 10; the extra bug hunters of `coordinator-idle-bug-hunt.md` are outside this count.
- Fewer: running workers are not stopped; a finished worker is not replaced. More: at most one new worker a minute.
- A warning (`allowed_warning`) changes nothing: the hourly count goes on. Refused (`rejected`): no new worker until `resetsAt`.
- Under 4 only for the reasons in `coordinator-limits.md` (queue limit, no free step).

Related: docs/memory/workflow/coordinator-limits.md, docs/memory/workflow/coordinator.md
