---
name: session-lifecycle
description: From a green PR to the session's end: the ready label, then unsubscribe and end (since 2026-11-19: no wait for the merge, no next step), no base merge after the label, a PR sent back, an idle draft
---

## What do I do when my PR is green?
- Merge `origin/development` once and run your checks (`session-checks.md`) before the label; that is the last refresh from your side.
- Put the `ready` label on it: `gh api -X POST repos/kodpen/pinegrapcms/issues/<n>/labels -f 'labels[]=ready'` (a PR's labels endpoint sits under `issues/`; a merge order, if any, goes in the PR body).
- Then unsubscribe from the PR's activity, set no check-in and end. A standing session (architect, coordinator, merging session) goes back to its own queue and does not follow the PR.
- The Claude desktop app archives a worker when its PR merges (the account's auto-archive setting): that is wanted, so no binding and no `set_monitor`.

## What may I not do after the label?
- Merge `development` into the PR, rebase it or re-run its checks: keeping it current is the merging session's job, and its train settles conflicts (`merge-train.md`).
- This holds against the PR subscription's own text, which tells a session to merge the base branch on a conflict and to keep a series of check-ins (#1683).

## Why does nobody wait for the merge?
- Architect (2026-11-19): until then a worker waited subscribed and took its plan's next step itself, three at most. A measured worker spent 48% of its use after the label, and a next step runs every call on the old step's context (`quota-use-measured.md`).
- The plan's next step goes to a new worker once this one has landed (`coordinator-worker-steps.md`); the plan file and the merged PR are the hand-over.

## What if my PR is sent back?
- The merging session fixes a small blocker on the branch itself and says so in a comment.
- A larger one gets one comment naming it, and the PR stays out of the train. Its coordinator sees that at a check-in and gives the branch to a new session, which pushes the fix and marks it ready again (`coordinator.md`).

## What happens to a draft nobody pushes to?
- No push for 12 hours, no `Decision:` line on an open issue, no owner check named in its body (#3897): a coordinator's worker continues it on the same branch (merge `origin/development` as a new commit, never rebase, amend or force-push; finish what the body lists; "continued on <date> (#3897)" in the body; ready and label when green). Nobody re-claims its files elsewhere; a body naming a wait keeps the hold; the coordinator's one-hour notice (`coordinator.md`) comes first.

## What if I need a decision?
- Never ask the owner in your reply or the PR body. Ask the architect in one agent-channel message (`architect-asking.md`; first search the decisions list and the plan so the question is not asked twice) and name it in the PR body (`Decision: channel message <id>`); no `architect` issue is opened.
- A session without a channel key writes the text in the PR body under `## Decision question` instead; its coordinator carries it into the channel.
- Then turn the PR back to draft (`gh pr ready <n> --undo`; in a cloud session `mcp__github__update_pull_request` with `draft: true`) and take its `ready` label off (`gh api -X DELETE repos/kodpen/pinegrapcms/issues/<n>/labels/ready`). A PR waiting on a decision cannot go into a train, so it gives its place in the queue to work that can.
- Then end the session: the draft PR body and the plan line are the hand-over (what is built, what waits on the decision). Never wait for the answer: a waiting session pays its whole context on every check-in. The architect answers in the channel (`architect.md`); a new session then continues the branch, follows the decision and marks the PR ready again (`coordinator-limits.md`). The architect records the decision in memory; the worker does not.

Related: docs/memory/workflow/working-mode.md, docs/memory/workflow/merge-review.md, docs/memory/workflow/architect.md
