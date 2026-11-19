---
name: coordinator-worker-steps
description: A worker does one task and ends at the ready label (since 2026-11-19; three steps before); every step goes to a new session, never to an idle or ended worker; work in one file or screen is one task
---

## How many steps does one worker session do?
- One: it ends when its PR has the `ready` label (`session-lifecycle.md`).
- Architect (2026-11-19): changed from three steps in a row with a wait for each merge, on the measurement in `quota-use-measured.md`.

## Who gets the plan's next step?
- A new session, cloud or desktop, with its own short brief, once the step before it has landed (`coordinator.md`, `coordinator-worker-brief.md`). A coordinator never sends a step to a worker that is waiting or done.

## What is given out as one task?
- Work in one file or on one screen that is known when the task is given: two fixes of one screen go to one worker as one PR, inside the small-PR limit. The second fix then costs about a third less than in a new session, since the reading is paid once and nothing waits in between.

## Why?
- A session pays for its whole context on every call. A new worker starts at 92,000 tokens and reads itself to about 120,000; a session that has finished a product step stands at 170,000 or more, so a next step in it would cost about half as much again on every call.
- The wait for the merge is paid too: a wake more than an hour after the last call writes the session's whole context into the cache again. A measured worker spent 48% of its use after the label.
- On 2026-11-11 and 2026-11-12 a coordinator sent idle sessions the next step itself at each 30-minute check-in: some sessions did more than 200 tasks, and the account's quota for the period was spent in one day.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/coordinator-worker-brief.md, docs/memory/workflow/coordinator-workers.md, docs/memory/workflow/session-lifecycle.md
