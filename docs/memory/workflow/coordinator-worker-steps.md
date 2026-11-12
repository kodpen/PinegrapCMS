---
name: coordinator-worker-steps
description: A worker does at most three steps in its life, whoever hands them; a coordinator starts a new session for every step it gives and never feeds a step to an idle or ended worker
---

## How many steps does one worker session do?
- At most three in a row, counted from the session's start, then it hands over through the plan file and ends (`session-lifecycle.md`).
- The count covers every step the session does, the ones it takes itself after a merge and the ones a coordinator's brief gives it. A new brief does not restart the count.

## Does a coordinator give a new step to a worker that is waiting or done?
- No. Every step a coordinator hands out goes to a new session, cloud or desktop, with its own short brief (`coordinator.md`, `coordinator-worker-brief.md`).
- A worker whose PR merged takes its plan's next step itself, under the three-step limit; when it has ended or waits on someone else, its place gets a new worker (`coordinator-workers.md`). The coordinator never sends it one more step.

## Why?
- A session pays for its whole context on every wake; each step added to a long session costs more than the step before.
- On 2026-11-11 and 2026-11-12 a coordinator briefed its workers to stop after one step, then at each 30-minute check-in sent the idle session the next step itself. Each brief looked new to the worker, so its three-step count never filled: some sessions did more than 200 tasks, and the account's quota for the period was spent in one day.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/coordinator-worker-brief.md, docs/memory/workflow/coordinator-workers.md, docs/memory/workflow/session-lifecycle.md
