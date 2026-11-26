---
name: coordinator-worker-brief
description: A coordinator's worker brief (task, memory folder, the end at the ready label) and how a worker is started: a session or a background worker, as the coordinator chooses (owner 2026-11-27)
---

## What does a worker brief say?
- The step or task, the memory folder to read and "follow CLAUDE.md" (`coordinator.md`).
- Its last line is the lifecycle: when CI is green the `ready` label, then unsubscribe from the PR and end; no wait for the merge and no next step (`session-lifecycle.md`).
- Work in one file or on one screen goes into one brief (`coordinator-worker-steps.md`).

## Why does the brief end the session at the label?
- Architect (2026-11-19): until then a brief never said "stop after this step": the worker waited for the merge and took up to three steps, to save each step's fresh start. The measurement showed the wait and the grown context cost more than the start (`quota-use-measured.md`).

## How is a worker started?
- As the coordinator chooses: a cloud session (`create_session`, `coordinator-limits.md`), a desktop session it starts (`start_session`), or a background worker of its own window.
- Owner (2026-11-27, agent channel message 14725): "koordinatörlere arkaplan işçisi açmalarını engelleyen koordinatör kuralını kaldıralım. koordinatörler nasıl isterse öyle çalışsınlar".
- Why: the desktop app starts a new session only on the owner's own word in that window, so with the owner away a desktop coordinator could start no worker (k2, that night); a background worker has no such gate.
- Until then a worker was never a Workflow run or a sub-agent: decided while a worker still waited for its merge, which a sub-agent cannot. A worker ends at the `ready` label since 2026-11-19, so that reason was gone.
- What stays, however it is started: one task and one PR, its own draft PR as the claim, the brief above, the end at the label. A background worker takes one of the coordinator's places, and the coordinator still writes no code itself.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/session-lifecycle.md, docs/memory/workflow/coordinator-worker-steps.md
