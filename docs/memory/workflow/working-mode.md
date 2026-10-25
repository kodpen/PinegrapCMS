---
name: working-mode
description: Working rules (2026-10-25) for every session: one open PR, no stacked branches, no issues, ready label when green, the next step when merged, PR size, queue limit, changelog via the PR body
---

## Why these rules?
- 2026-10-25: about fifteen sessions on four accounts work at once and one session merges. Quotas ran out while PRs waited: every merge made open PRs conflict (mostly in `tr.json`), each session merged `development` again and was woken by every CI event, and review chains cost millions of tokens per PR.

## How many PRs may a session have open?
- One, the draft that claims the task included (`CLAUDE.md`, Claim work). Branch only from `development`; never from an open PR. The next task, or a plan's next step, starts after the PR is merged or handed over. Work that needs an unmerged PR waits for it.

## Are issues used?
- No: no claim, hand-off, decision request or follow-up issue. A claim is a draft PR; a hand-off is the PR itself; a question for the owner is asked in the session's reply and kept in the plan file's open questions (`CLAUDE.md`, Fixed rules 6).

## What do I do when my PR is green?
- Put the `ready` label on it: `gh api -X POST repos/kodpen/pinegrapcms/issues/<n>/labels -f 'labels[]=ready'` (a PR's labels endpoint sits under `issues/`; the merge order, if any, is in the PR body). Then cancel your check-ins and wait, still subscribed to the PR's activity. A ready PR gets no CI events (nothing is pushed to its branch), so only the merging session's comment and the merge wake you. Do not merge `development` into it, rebase it or re-run its checks: keeping it current is the merging session's job (`merge-train.md`).
- Before the PR is marked ready, merge `origin/development` once and run your checks (`session-checks.md`); that is the last refresh from your side.

## How much checking, how many agents?
- The tests of your area and what the change touches, no sandbox without a reason, no sub-agents or review chains: `session-checks.md`.

## How big is a PR?
- About 1,000 product lines or fewer: changed lines in files under `pinegrap/`, `.min.js` bundles and language files not counted; tests, tools and memory never count. A plan's steps are cut to it when the plan is written ([[merge-flow-tools]]). A finished PR over it still lands, in a train of its own; the split is asked of the next step.

## What do I do when my PR is merged?
- Check the queue limit (below). Under it, take your plan's next step that no open PR holds and claim it with a draft PR (`CLAUDE.md`, Claim work); with no plan, no free step or the queue over the limit, end. Before this, every session stopped at the label and had to be told to go on.
- At most three steps in a row, then hand over through the plan file and end: a long session pays for its whole context on every wake. At most one safety check-in while a PR waits; never poll.

## When may nobody start a task?
- While more than 15 PRs are open, drafts and `merge-train/*` PRs not counted (the command is in `CLAUDE.md`, Working mode 8). Checked before the draft PR that claims the task; a session whose work is done still marks its PR ready. About 6 to 8 working sessions at a time, each on its own area (the folder table in `CLAUDE.md`); two sessions never hold the same files.

## What about changelog.txt?
- A PR does not edit it. The Changelog section of its body names the `[NEW]` line, the `[SCHEME]` item or "none"; the merging session writes it when it lands the PR (`merge-train.md`).

## Which PRs skip the train?
- A PR that changes only `docs/`, `CLAUDE.md` or `.github/`: the merging session lands it alone as soon as it is green, so every new session starts under the current rules.

## What if my PR is sent back?
- The merging session fixes a small blocker itself; a larger one gets a comment on the PR, which wakes you: push the fix and mark it ready again (`merge-review.md`).

Related: docs/memory/workflow/session-checks.md, docs/memory/workflow/merge-train.md, docs/memory/workflow/merge-train-landing.md, docs/memory/plans/merge-flow-tools.md
