---
name: claim-work
description: How a session claims work before starting it: the open-PR check, the claiming draft PR and its title, the race between two drafts, finishing and marking ready
---

## How is work claimed?
- With a draft PR on GitHub, never an issue. The only issues between sessions are decision questions (`architect.md`) and the test coordinator's `bug` reports (`test-coordinator.md`).
- Use REST (`gh api repos/kodpen/pinegrapcms/…`): `gh issue`, `gh pr` (GraphQL) and the search API are blocked in cloud sessions.

## 1. What is checked first?
- The open PRs, drafts included (command in `CLAUDE.md`, Claim work).
- A PR on the same plan file (any step) or the same files (`…/pulls/<n>/files`) holds it: do not start, and tell the owner which PR.
- A draft with no push for 12 hours and no stated wait is continued on its own branch by a coordinator's worker (`session-lifecycle.md`), never re-claimed.
- One plan's steps run in parallel only where the owner allowed it or the coordinator chose it (they touch different files).
- The queue limit (`CLAUDE.md`, Working mode 8; `working-mode.md`).

## 2. How is the claim made?
- Branch from `origin/development`, push the first commit (the failing test or the first slice) and open a **draft** PR to `development`.
- Title: `[plan:<plan file name>] <steps> — <topic>`, or `[iş] <topic>` outside a plan. The body names the step and the files it will touch.
- List the open PRs again: if a lower-numbered draft on the same plan or files appeared, close yours and stop.
- Small fixes, memory-only edits and reviews skip the draft, not the check.

## 3. How is it finished?
- Mark the steps done in the plan file with the PR number and push.
- Mark the draft ready (`gh pr ready <n>`; in a cloud session `mcp__github__update_pull_request` with `draft: false`): CI runs and the PR counts in the queue.
- When CI is green: the `ready` label and the wait (`session-lifecycle.md`, `CLAUDE.md` Working mode 2).

Related: docs/memory/workflow/working-mode.md, docs/memory/workflow/session-lifecycle.md
