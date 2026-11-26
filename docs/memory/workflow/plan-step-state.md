---
name: plan-step-state
description: Where a plan step's state is kept: the PR titled for the plan, never a done mark a worker writes into the plan file; the plan file is the architect's alone; a coordinator writes the channel tasks
---

## Who marks a step done?
- Nobody, in the plan file. Architect (2026-11-26): a worker's PR no longer writes "done" and its number into its plan's file, and changes no line of it.
- A step's state is its PR: a draft titled `[plan:<plan>] <steps> — <topic>` holds the step, an open PR has it ready, a merged one has done it. `php tools/plan_state.php <plan>` prints that ([[plan-state-tool]]); `php tools/free_work.php` lists a step as free only when no such PR holds it or has landed (`coordinator-context.md`).
- So the title must name every step the PR finishes, by the ID the plan gives it: a step missing from the title stays free and is given out again.
- The done marks already written in plan files stay and still count for `free_work.php`.

## Who changes a plan file?
- The architect alone: a correction, a new step, a changed order, a line a decision frees. It may do so while a step's PR is open, which the done marks made impossible: its corrections had to go into `<plan>-fixes.md` files beside the plan.
- One PR of a worker still touches it: the plan's close-out step, which moves the lasting decisions to the topic folder and deletes the file (`CLAUDE.md`, Memory).

## Where does a worker write what the next step must know?
- Into the memory file its PR writes in the topic folder: what was built, what it left, what the next step finds. The next worker reads the plan's step and that file; the plan file and the landed PR are the hand-over (`session-lifecycle.md`).
- A fault in the plan itself (a step that cannot be built as written, a missing step) goes to the architect in the agent channel (`architect-asking.md`), not into the plan by the worker's own edit.

## Who shows the state in the agent channel?
- The coordinator that gives the step out: `php tools/plan_state.php <plan> --tasks` when it starts a worker on the plan and when one of the plan's PRs has landed. The channel's Tasks tab and Board then show every running plan's steps as todo, doing, waiting or done.
- The tasks are written from the PR titles, so GitHub stays the record: after dev is set up again one run puts the board back. A state kept only in the channel would be lost with it.
- The run is one command inside a turn the coordinator has anyway (a worker's start, the check-in that saw the landing), chained to that turn's own command where it can be (`… && php tools/plan_state.php <plan> --tasks`). Never a wake, a `send_later` or a turn of its own for it: the script calls no model, its cost is its few printed lines, and a turn of its own would have the session read its whole context again. Owner (2026-11-26): the board stays on that condition.

## Why?
- Owner (2026-11-23): asked whether memory and plans kept in git slow the work, he took the architect's answer and had the two slow places fixed (`decisions-made-workspace-49.md`). One was a docs-only PR's full check ([[docs-only-pr-checks]], #11196); the other was the plan file shared by its workers and the architect.
- Plan texts and their drawings stay in git: a worker reads them in its clone with no key, and a drawing is an HTML sheet a channel note cannot hold.
- Built by #11196 (the light check), #11199 (`plan_state.php`) and #11323 (`free_work.php` counts every landed PR of a plan, not only the last 60 days' ones).

Related: docs/memory/workflow/claim-work.md, docs/memory/workflow/coordinator-worker-steps.md, docs/memory/workflow/plan-state-tool.md
