---
name: working-mode
description: Working rules (2026-10-25) for every session: why they exist, one open PR, no stacked branches, no issues, PR size, queue limit, the changelog through the PR body, docs-only PRs outside the train
---

## Why these rules?
- 2026-10-25: about fifteen sessions on four accounts work at once and one session merges. Quotas ran out while PRs waited: every merge made open PRs conflict (mostly in the old single `tr.json`), each session merged `development` again and was woken by every CI event, and review chains cost millions of tokens per PR.
- They are the sessions' own arrangement, changed by a PR that gives its reason; never written as an owner decision (`CLAUDE.md`, Fixed rules 6).

## How many PRs may a session have open?
- One, the draft that claims the task included (`CLAUDE.md`, Claim work). Branch only from `development`, never from an open PR. A plan's next step starts after the previous step is merged; work that needs an unmerged PR waits for it.
- From green CI to the next step: `session-lifecycle.md`.

## Are issues used?
- No: no claim, hand-off, decision request or follow-up issue. A claim is a draft PR, a hand-off is the PR itself, and a question for the owner is asked in the session's reply or under `## Questions for the owner` in the PR body (`coordinator.md`), then kept in the plan file's open questions.

## How much checking, how many agents?
- The tests of your area and what the change touches, no sandbox without a reason, no sub-agents or review chains: `session-checks.md`.

## How big is a PR?
- About 1,000 product lines or fewer: changed lines in files under `pinegrap/`, `.min.js` bundles and language files not counted; tests, tools and memory never count. A plan's steps are cut to it when the plan is written. A finished PR over it still lands, in a train of its own; the split is asked of the next step.

## When may nobody start a task?
- While more than 15 PRs are open, drafts and `merge-train/*` PRs not counted (the command is in `CLAUDE.md`, Working mode 8). Checked before the draft PR that claims the task; a session whose work is done still marks its PR ready.
- Only work the merging session can take holds a place: a PR waiting on the owner's answer goes back to draft until the answer comes (`session-lifecycle.md`).
- Each account's coordinator sets how many workers run (`coordinator.md`), and its workers hold at most 4 of the 15 places, so one fast account does not fill the queue for the others. Two sessions never hold the same plan or the same files.

## What about changelog.txt?
- A PR does not edit it. The Changelog section of its body names the `[NEW]` line, the `[SCHEME]` item or "none"; the merging session writes it when it lands the PR (`merge-train.md`).

## Which PRs skip the train?
- A PR that changes only `docs/`, `CLAUDE.md` or `.github/`: the merging session lands it alone as soon as it is green, so every new session starts under the current rules. When a train is already waiting and both regenerate the same index, it rides that train instead, so `development` does not move under it.

Related: docs/memory/workflow/session-lifecycle.md, docs/memory/workflow/session-checks.md, docs/memory/workflow/merge-train.md, docs/memory/workflow/coordinator.md, docs/memory/plans/merge-flow-tools.md
