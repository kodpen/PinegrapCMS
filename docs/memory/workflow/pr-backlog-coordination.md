---
name: pr-backlog-coordination
description: How a backlog of open PRs on development is cleared by one coordinating session: claim issue, take-over comments, active-branch rule, merge order, tr.json re-conflicts, English bodies
---

## Who clears a backlog of open PRs?
- One coordinating session claims the whole list in a `sahiplenildi` issue (first time: #1133, 2026-10-21) and leaves an English take-over comment on every PR; from then on no other session pushes to those branches. A branch with commits in the last hour belongs to the session pushing to it until that session comments "ready" on the PR.

## In which order do they merge?
- A PR that carries another open PR's commits waits for it; the PR that rewrites the memory tree merges last.
- `tr.json` keys are appended at the end of the file, so after each merge every other `tr.json` PR conflicts again: merge `development` into the next one, resolve, wait for CI, then merge. Generated indexes (`docs/memory/README.md`, folder READMEs) are never resolved by hand; run `php tools/memory_index.php`.

## What happens to each PR before the merge?
- Confirmed review findings are fixed on the branch, the body is rewritten in English, the Claude Code footer removed, CI must be green on the final head, the squash commit keeps the PR title and gets a short English message, and the PR's own claim is closed with the PR number.

Related: docs/memory/workflow/git-branches-and-shared-files.md, docs/memory/workflow/owner-rules.md
