---
name: coordinator-worker-brief
description: What a coordinator's worker brief says and never says: no "stop and report", no "one step only", the lifecycle as its last line; workers are real sessions, never Workflow or sub-agent runs
---

## What does a worker brief say?
- The step or task, the memory folder to read and "follow CLAUDE.md" (`coordinator.md`).
- Its last line is the lifecycle: when CI is green the `ready` label, then wait subscribed to the PR; the merge wakes the session, which claims its plan's next free step itself; at most three steps in a row, then it hands over through the plan file and ends (`session-lifecycle.md`).
- A worker in the Claude desktop app also binds its PR to its session right after opening it and turns the app's archive-on-close off (`session-pr-binding.md`).

## What does a brief never say?
- "Stop after this step", "label ready, then stop and report", "do not wait for the merge", "do not take the plan's next step", or the same in other words.
- Why: a worker follows its brief over the general rule (CLAUDE.md, Working mode 6), so a brief that says stop silently overrides the lifecycle.
- Seen on 2026-11-11 and 2026-11-12: the local coordinator's briefs ended "label-ready, then stop and report". Each worker ended after one step, the app archived it when its PR merged, and every next step of a plan needed a new session: a fresh start's cost and the wait for it, step after step.
- The line came from an earlier design that ran workers as Workflow sub-agents, which cannot wait for a merge; it stayed when the workers became sessions.

## Why are workers real sessions?
- A worker is a session: a cloud session (`create_session`, `coordinator-limits.md`) or a desktop session started by the coordinator (`start_session`). Never a Workflow run or a sub-agent.
- A sub-agent ends when it returns. It cannot stay subscribed to its PR, is not woken by the merge and cannot take the plan's next step, so each step of a plan would cost a new start, and nobody answers the merging session when it sends the PR back.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/session-lifecycle.md, docs/memory/workflow/session-pr-binding.md
