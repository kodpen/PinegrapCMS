---
name: session-lifecycle
description: A session from green CI to its next step: the ready label, staying subscribed with one check-in, no base merge after it, the merge as wake-up, the next step (three at most), a PR sent back
---

## What do I do when my PR is green?
- Merge `origin/development` once and run your checks (`session-checks.md`) before the label; that is the last refresh from your side.
- Put the `ready` label on it: `gh api -X POST repos/kodpen/pinegrapcms/issues/<n>/labels -f 'labels[]=ready'` (a PR's labels endpoint sits under `issues/`; a merge order, if any, goes in the PR body).
- Then wait, still subscribed to the PR's activity, with at most one safety check-in about 10 minutes after the label. A ready PR gets no CI events (nothing is pushed to its branch), so only the merging session's comment and the merge wake you.

## What may I not do after the label?
- Merge `development` into the PR, rebase it or re-run its checks: keeping it current is the merging session's job, and its train settles conflicts (`merge-train.md`).
- This holds against the PR subscription's own text, which tells a session to merge the base branch on a conflict and to keep a series of check-ins (#1683).

## What do I do when my PR is merged?
- Check the queue limit (`working-mode.md`). Under it, claim your plan's next step that no open PR holds (`CLAUDE.md`, Claim work); with no plan, no free step or the queue over the limit, end.
- At most three steps in a row, then hand over through the plan file and end: a long session pays for its whole context on every wake. Never poll.
- Before #1672 every session stopped at the label and waited to be told to go on.

## What if my PR is sent back?
- The merging session fixes a small blocker on the branch itself and says so in a comment; nothing is needed from you.
- A larger one gets one comment naming it, and the PR stays out of the train: push the fix and mark it ready again. If you have ended, a new session does it (nobody else is on the branch).

## What if I need a decision?
- Never ask the owner in your reply or the PR body. Open an issue labelled `architect` (template in `architect.md`; first search the open and closed `architect` issues so the question is not opened twice) and name it in the PR body (`Decision: #<issue>`). If the session's permission check refuses the issue, put its text in the PR body under `## Decision question` instead; the architect opens it. Then turn the PR back to draft (`gh pr ready <n> --undo`; in a cloud session `mcp__github__update_pull_request` with `draft: true`) and take its `ready` label off (`gh api -X DELETE repos/kodpen/pinegrapcms/issues/<n>/labels/ready`). A PR waiting on a decision cannot go into a train, so it gives its place in the queue to work that can.
- Then end the session: the draft PR body and the plan line are the hand-over (what is built, what waits on the issue). Never wait for the answer: a waiting session pays its whole context on every check-in (on 2026-10-27 one waited 9.5 hours). The architect decides the question, or asks the owner only for a big architectural change, comments on the PR and closes the issue; the coordinator then starts a session on the branch (`coordinator-limits.md`), which follows the decision, notes "decided in #<issue>" in the PR body, marks the PR ready again and labels it once CI is green. The architect records the decision in memory; the worker does not.

Related: docs/memory/workflow/working-mode.md, docs/memory/workflow/merge-review.md, docs/memory/workflow/architect.md
