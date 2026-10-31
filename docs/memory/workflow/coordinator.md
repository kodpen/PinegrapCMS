---
name: coordinator
description: Coordinator session (pilot, 2026-10-26): one per account, keeps its cloud workers on free plan steps, looks at them every 10 minutes, writes no code, merges nothing; questions go to the architect
---

## Why a coordinator?
- Sessions waited to be told to go on at 20-30% quota use; a coordinator keeps its workers busy.

## What does it do each turn?
- Reads the state from GitHub and the plan files, not its memory: open PRs and drafts, the queue count (Working mode 8); `plans/README.md`, open plan steps and `bug` issues (`test-coordinator.md`) only when a slot is free.
- Picks work no open PR holds: one plan or area per worker, never two workers on the same files or the same plan (Claim work, step 1); steps of one plan in parallel when the owner assigned them so or when they touch different files.
- Starts each worker as a cloud session with a short brief: the plan step or task, the memory folder, and "follow CLAUDE.md". The worker claims with its own draft PR and works as every session does, its plan's next steps included (Working mode 2 and 6).
- Keeps 3 to 6 workers busy (since 2026-10-31); fewer only as `coordinator-limits.md` allows.
- Keeps a to-do list of its workers' PRs (`coordinator-todo-list.md`).
- Chooses the work itself, never asking the owner or the architect: which free step, the order, how many workers, which steps run in parallel. Only a decision question goes to an `architect` issue; the architect brings the answer to the PR (`architect.md`).
- Writes no product code, opens no PR, merges nothing, does not review: the merging session does.

## What are its limits?
- It reaches only its own account.
- `create_session` works on any turn, also one woken by a check-in, an event or another session (tested 2026-10-31): it starts workers and hands over without waiting for the owner.
- A worker counts once its draft PR is on GitHub.

## What does a check-in look at?
- Every 10 minutes (a session cron on off-minutes, such as `3,13,23,33,43,53 * * * *`): one listing of the open PRs, drafts included, against the previous one. Nothing new: no message, no more reading.
- Its workers' PRs: merged with no next draft 10 minutes later (its slot is free); sent back by the merging session and not pushed to within 30 minutes (a new worker for that branch); a draft with no push for an hour (named to the owner once: a prompt, limit or error stopped it); a worker with no draft 30 minutes after its start (`get_session`; replace a failed one).
- The merging session: a `ready` PR with no update for 45 minutes and no `merge-train/*` PR open, or a train PR with none for 45 minutes, means the merger stopped: tell the owner once, with the PR numbers. Also an `architect` issue with no analysis for an hour.
- Why 10 minutes: at 30 a stuck worker waited half an hour. Quota guard, worker range, decided drafts: `coordinator-limits.md`.

## When does it hand over to a fresh coordinator?
- Past 350k tokens of context (`get_session` `context_usage.used_tokens`, at a check-in): each wake pays for all of it.
- The workers go with it. Its note: each worker's session id and PR, waits it named to the owner, its to-do list, and the schedules the new one sets up (each cron's minutes and prompt, check-ins, routines).
- It starts the new one with `create_session` and the note, sends each running worker the new coordinator's session id (`send_message`), cancels its own schedules and ends. A worker's failure event still wakes the old one: it only forwards it to the new one. It hands over on any turn.
- Every `create_session` (worker or new coordinator) passes `source_url` `https://github.com/kodpen/PinegrapCMS` and `source_revision` `development`, else the session has no repository.

## How is one started?
- A new desktop session on the account, told: "You are the coordinator: read `CLAUDE.md` and `docs/memory/workflow/coordinator.md`, then start the workers and keep the 10-minute check-in."

Related: docs/memory/workflow/working-mode.md, docs/memory/workflow/merge-train.md, docs/memory/workflow/merge-review.md
