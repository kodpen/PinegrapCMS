---
name: coordinator-context
description: Keeping a coordinator's context small (2026-11-04): what its first turn and each check-in read, tools/free_work.php for free work, what it never reads whole
---

## Why?
- On 2026-11-04 a new coordinator reached about 220k tokens of context after its first scan for work.
- About 75k is in place before any call: the system prompt, tool schemas and CLAUDE.md.
- Its first turns added the rest:
  - the predecessor's transcript through `list_events` (35k characters);
  - `plans/README.md` whole (23k);
  - a grep over every plan's steps (12k);
  - open issues listed twice (22k) and closed PRs (6k);
  - its memory files read a second time after a stale clone;
  - `list_triggers` with no filter (28k).
- Tool output runs at about 2 characters a token, not 4.

## What does it read?
- **First turn:** it fetches and reads from `origin/development`; a stale clone means reading everything twice. Then `coordinator.md`, `coordinator-limits.md`, `coordinator-workers.md` and `coordinator-todo-list.md`; anything else only when a step needs it.
- **When a slot is free:** `php tools/free_work.php` only (about 6 KB). It prints:
  - the queue count;
  - the plan steps not done and held by no open PR, each with its "After";
  - the open `architect` issues (no `bug` issue: they are not given out, `coordinator-no-plans.md`).
  - Steps are read in every format the plans use: `- AP3 …`, `- **AP3** …`, `- E20 (S) …`, `2. TD14 …`, indented `  - V3 …` (an indented line with the same ID or no list mark is more of the step above), `- Done: N1 #n, …` lists, `D5 part 4` from "Next: part 4", the own-looks round's `<name>-1`/`-2` (four templates at a time), and under a steps heading the design templates' numbered ones (`- 4 Topic: …`, `- 4 (done, #n): …`). Not listed: steps of the owner ("(S, owner's machine)"), lines without `free` where the holder marks free lines `free`, and the steps after the first open one of a plan whose steps wait for the one before. Until #8204 these were listed wrongly or missed. A new step format needs a case in `tests/free_work_test.php`; `--worktree` shows what an unpushed plan edit lists.
  
  Then it opens only the plan file of the step it gives out, sliced to that step.
- **Each check-in:** one open-PR listing with `--jq` (CLAUDE.md, Claim work), and `get_session` only for a worker it checks.

## What does it never do?
- Read `plans/README.md`, a folder `README.md` index or a plan file whole; grep across all plans; list issues or closed PRs itself.
- Use `list_events` on another session: the hand-over note comes in its first message, and a worker's state is its PR.
- Call `list_triggers` without `enabled: true` and `limit: 20`.
- Load tools it will not use: each ToolSearch batch adds 3–9k tokens.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/coordinator-limits.md, docs/memory/workflow/coordinator-no-plans.md
