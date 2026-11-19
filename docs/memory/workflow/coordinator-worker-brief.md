---
name: coordinator-worker-brief
description: A coordinator's worker brief: the task, the memory folder, and as its last line that the session ends at the ready label (since 2026-11-19); workers are real sessions, never sub-agent runs
---

## What does a worker brief say?
- The step or task, the memory folder to read and "follow CLAUDE.md" (`coordinator.md`).
- Its last line is the lifecycle: when CI is green the `ready` label, then unsubscribe from the PR and end; no wait for the merge and no next step (`session-lifecycle.md`).
- Work in one file or on one screen goes into one brief (`coordinator-worker-steps.md`).

## Why does the brief end the session at the label?
- Architect (2026-11-19): until then a brief never said "stop after this step": the worker waited for the merge and took up to three steps, to save each step's fresh start. The measurement showed the wait and the grown context cost more than the start (`quota-use-measured.md`).

## Why are workers real sessions?
- A worker is a session: a cloud session (`create_session`, `coordinator-limits.md`) or a desktop session started by the coordinator (`start_session`). Never a Workflow run or a sub-agent.
- Decided while a worker still waited for its merge, which a sub-agent cannot; kept on 2026-11-19 without a new look. A session's record can be read by itself, which is how its use was measured.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/session-lifecycle.md, docs/memory/workflow/coordinator-worker-steps.md
