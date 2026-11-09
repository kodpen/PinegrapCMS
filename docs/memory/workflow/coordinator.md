---
name: coordinator
description: Coordinator session: one per account, keeps its cloud workers on free plan steps, looks at them every 30 minutes, writes no code, merges nothing; questions go to the architect
---

## Why a coordinator?
- Sessions waited to be told to go on at 20-30% quota use; a coordinator keeps its workers busy.

## What does it do each turn?
- Reads the state from GitHub, not memory: open PRs and drafts, the queue count (Working mode 8); free steps and issues only when a slot is free, by `php tools/free_work.php` (`coordinator-context.md`).
- Picks work no open PR holds: one plan or area per worker, never two workers on the same files or the same plan (Claim work, step 1); steps of one plan in parallel when the owner assigned them so or they touch different files.
- Starts each worker as a cloud session with a short brief: the step or task, the memory folder, "follow CLAUDE.md". The worker claims with its own draft PR and works as every session does, its plan's next steps included (Working mode 2 and 6).
- Keeps 8 workers working (`coordinator-workers.md`).
- Design templates are paused (2026-11-09): no template step and no new template; no bug hunt or `bug` issue (`coordinator-no-plans.md`).
- Keeps a to-do list of its workers' PRs (`coordinator-todo-list.md`).
- Chooses the work itself, never asking the owner or the architect: which free step, the order, how many workers, which steps run in parallel. Only a decision question goes to an `architect` issue; the architect brings the answer to the PR (`architect.md`).
- Writes no product code, opens, reopens or marks ready no PR, merges and reviews nothing.

## What are its limits?
- It reaches only its own account.
- `create_session` works on any turn: it starts workers and hands over without waiting for the owner.
- A worker counts once its draft PR is on GitHub.

## What does a check-in look at?
- Every 30 minutes by a `send_later` chain and an hourly routine re-arming it (`merger-wake.md`). One listing of the open PRs, drafts included, against the previous one; nothing new, no message.
- Its workers' PRs: merged with no next draft 30 minutes later (its slot is free); sent back by the merging session and not pushed to within 30 minutes (a new worker for that branch); a draft with no push for an hour (named to the owner once: a prompt, limit or error stopped it); a worker with no draft 30 minutes after its start (`get_session`; replace a failed one).
- The merging session: a `ready` PR with no update for 45 minutes and no `merge-train/*` PR open, or a train PR with none for 45 minutes, means the merger stopped: tell the owner once, with the PR numbers. Also an `architect` issue with no analysis for an hour.
- Archives its idle workers whose work is done, handed-over ones too (`coordinator-limits.md`).
- Model and effort, session sources, limit guard, decided drafts: `coordinator-limits.md`.

## When does it hand over to a fresh coordinator?
- Past about 200k tokens of context (`get_session` `context_usage.used_tokens`, at a check-in), up to depth 6: each wake pays for all of it. At depth 7 it works on, whatever its size (`coordinator-limits.md`).
- The workers go with it. Its note: each worker's session id and PR, waits it named to the owner, its to-do list, and the schedules the new one sets up (chain, routine, check-ins).
- First it deletes its own schedules (chain and routine, `delete_trigger`) and archives its finished workers, then starts the new one with `create_session` and the note, sends each running worker the new one's session id (`send_message`) and ends. The new one's first act is to archive it and any older coordinator of the account. A worker's failure event then reaches no one; the 30-minute check finds a stopped worker.

## How is one started?
- A new desktop session on the account, told: "You are the coordinator: read `CLAUDE.md` and `docs/memory/workflow/coordinator.md`, then start the workers and keep the 30-minute check-in."

Related: docs/memory/workflow/working-mode.md, docs/memory/workflow/merge-train.md
