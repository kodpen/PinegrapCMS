---
name: plan-state-channel
description: Plan (owner 2026-11-23): what slows the work with memory and plans in git: a docs-only PR's full check, the plan file shared with workers; a light check, step state as channel tasks; PS1-PS3
priority: 6
---

## What is asked?
- Owner (2026-11-23): asked whether memory and plans kept in git slow the work, he took the architect's answer and wants the two slow places fixed now (`workflow/decisions-made-workspace-49.md`).

## What slows, and what does not?
- A PR that changes only `docs/` runs every job of `.github/workflows/php-checks.yml` (it has no path rule) and then waits for the merging session: ten to thirty minutes for a decision of one line.
- A plan file is changed by its workers (the done marks) and by the architect (corrections). While a step's PR is open the architect may not touch the file, so corrections went into `-fixes.md` files beside the plan.
- Not slow: reading memory, which is local files and `grep`. And memory is not apart from code: 2064 of the 2603 commits that reached `development` in the week to 2026-11-23 touch `docs/memory`.

## What changes?
- PS1 (S, `.github/workflows/php-checks.yml`): when every changed file of a PR is under `docs/`, each job keeps its name and ends green after the memory check (`php tools/memory_index.php --files`) and the branch guards; nothing else runs. A PR that touches `CLAUDE.md`, `.github/` or any other path runs everything, and so does the push to `development`. The check names stay, so what the merging session reads as green (`workflow/merge-train.md`) holds.
- PS2 (S, `tools/`): `php tools/plan_state.php <plan file>` prints each step that has a PR, with its state from GitHub: a draft `[plan:<file>] <steps>` is held, an open one ready, a merged one done with its number. `--tasks` writes that as tasks of the agent channel with the caller's key: a task a step, titled `<plan> <step>`, status todo, doing, waiting or done, the PR's address in its description; only what differs is changed. Pure parts tested.
- PS3 (architect, docs only, after PS2 lands): a worker's PR no longer marks its step in the plan file. The state is what `plan_state.php` prints; a coordinator runs `--tasks` when it starts a worker and when a PR lands. The plan file is the architect's alone; what a worker wants the next step to know goes into the memory file its PR writes. `CLAUDE.md` (Claim work, step 3), `workflow/claim-work.md` and `coordinator-worker-steps.md` are rewritten to it.

## What does the owner see?
- The agent channel's Tasks tab and Board show every running plan's steps by state. An agent channel's tasks stay out of My Tasks and the Planning Board (`workspace/agent-channel-readers.md`).

## Why tasks, and why not only tasks?
- GitHub stays the record: the tasks are written from the PR titles, so after a reset of dev one run of `--tasks` puts the board back. A state kept only in the channel would be lost with it.
- Plan texts and their pictures stay in git: a worker reads them in its clone with no key, and a drawing is an HTML sheet a note cannot hold.

## Which order?
- PS1 and PS2 are apart and go to two workers at once; PS3 follows PS2. The close-out is PS3's: it writes the rule as a file of the `workflow/` folder and deletes this one.

Related: docs/memory/plans/agent-memory-search.md, docs/memory/workflow/claim-work.md
