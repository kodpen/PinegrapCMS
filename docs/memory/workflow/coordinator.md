---
name: coordinator
description: Coordinator session (pilot, 2026-10-26): one per account, keeps its cloud workers on free plan steps, looks at them every 10 minutes, carries owner questions, writes no code, merges nothing
---

## Why a coordinator?
- Every session stopped and waited to be told to go on, and each asked the owner in its own window, while the accounts' quotas were only 20-30% used halfway through their window. A coordinator keeps its account's workers busy and gathers the owner's questions in one place.

## What does it do each turn?
- Reads the state from GitHub and the plan files, never from its own memory: open PRs and drafts (the claims), the queue count (`CLAUDE.md`, Working mode 8); `docs/memory/plans/README.md` and the open plan steps only when a worker slot is free.
- Picks work no open PR holds: one plan or area per worker, never two workers on the same files or the same plan (Claim work, step 1); steps of one plan in parallel only when the owner assigned them so.
- Starts each worker as a cloud session with a short brief: the plan file and step (or the task), the memory folder to read, and "follow CLAUDE.md". The worker claims with its own draft PR and works as every session does, its plan's next steps included (Working mode 2 and 6).
- Keeps as many workers busy as the owner set for the account (3 to start with), fewer while the queue is near its limit.
- Writes no product code, opens no PR, merges nothing and does not review: that is the merging session's (`merge-train.md`, `merge-review.md`).

## How do questions reach the owner?
- A worker that needs the owner's decision writes it under `## Questions for the owner` in its PR body (draft or ready) and waits, subscribed. The coordinator reads that section of every open PR and asks the owner in one list.
- It brings the answer back as one PR comment quoting the owner's words; the comment wakes the worker, which records the decision with its reason (`decisions-made.md`; `CLAUDE.md`, Fixed rules 6). Cloud sessions cannot message other sessions, so the PRs carry both ways, across accounts too.

## What can it not do?
- The app starts a new session only on a turn the owner started. On a turn woken by a check-in or a GitHub event the coordinator offers the workers as cards instead, and the owner's short "go on" in the coordinator starts a batch.
- A worker card opens its cloud session only when the owner presses Start on it, so a worker counts as started, and as one of the account's workers, only once its draft PR is on GitHub.
- It reaches only its own account; each account runs its own coordinator, and the draft PRs keep them apart.
- Its context grows with each turn: it keeps no copy of reports, and once its context is large it ends and a fresh coordinator reads the state again.

## What does a check-in look at?
- Every 10 minutes (a session cron on off-minutes, such as `3,13,23,33,43,53 * * * *`): one listing of the open PRs, drafts included, against the previous one. Nothing new: no message and no further reading, so a quiet check-in stays cheap.
- Its workers' PRs: merged with no next draft 10 minutes later (the worker ended: its slot is free); sent back by the merging session and not pushed to within 30 minutes (a card for that branch); a new `## Questions for the owner`; a draft with no push for an hour (named to the owner once: a permission prompt, a usage limit or an error stops a session); a card with no draft yet (the owner is reminded once).
- Why 10 minutes: at 30, a worker that ended, got stuck or was sent back waited up to half an hour for the next look.

## How is one started?
- A new desktop session on the account, told: "You are the coordinator: read `CLAUDE.md` and `docs/memory/workflow/coordinator.md`, then start the workers and keep the 10-minute check-in."

Related: docs/memory/workflow/working-mode.md, docs/memory/workflow/merge-train.md, docs/memory/workflow/merge-review.md
