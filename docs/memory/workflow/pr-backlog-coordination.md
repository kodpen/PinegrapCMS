---
name: pr-backlog-coordination
description: The one session that merges PRs on development: claim, take-over comments, active branches, merge order, the tr.json chain and its conflicts, stacked PRs, semantic conflicts, English bodies
---

## Who merges PRs into development?
- Owner (2026-10-24): about ten sessions open PRs and one session merges them all; the others do not merge. Each round it lists the open PRs (head, `mergeable_state`, CI on the head), reviews each one once and fixes only blocking findings on the branch (`merge-review.md`), cleans body and comments, merges in a train (`merge-train.md`) and closes the PR's claim.

## Who clears a backlog of open PRs?
- One coordinating session claims the whole list in a `sahiplenildi` issue (first time: #1133, 2026-10-21; again #1426, 2026-10-23) and leaves an English take-over comment on every PR it pushes to; from then on no other session pushes to those branches. A branch with commits in the last hour belongs to the session pushing to it until that session comments "ready" on the PR.
- No other session merges (owner, 2026-10-25: `working-mode.md`); the merging session re-checks a PR's merge state right before each merge, never trusting an earlier "clean".

## In which order do they merge?
- A PR that carries another open PR's commits waits for it; the PR that rewrites the memory tree merges last.
- `tr.json` keys are appended at the end of the file, and the last key's comma changes with each append, so every two `tr.json` PRs conflict. They go into one merge train together and are settled there (`merge-train.md`), until the split in `docs/memory/plans/tr-json-split.md` lands.
- A `tr.json` conflict: start from `development`'s file and append, at the end, the keys the PR added (absent from the merge base) with the PR's values; a key whose value the PR changed takes the PR's; then `php tools/check_lang.php` (no key twice). `changelog.txt`: `development`'s lines plus the PR's own: a `[NEW]` line, or its item added to the one `[SCHEME]` line.
- Generated indexes (`docs/memory/README.md`, folder READMEs) are regenerated with `php tools/memory_index.php`, but the key-rules block between the markers is hand-written: keep the lines both sides added. Taking one side's README whole drops the other side's key rule (#1382 lost one this way, restored before merging).

## How is a stacked PR brought up to date after its base PR was squash-merged?
- Not by rebasing someone else's branch. First merge the base PR's final head into it (a normal merge). Then merge `development` with that head as the merge base: `git merge-tree --write-tree --merge-base=<base PR head> HEAD origin/development`, then `git commit-tree <tree> -p HEAD -p origin/development`. Only the stacked PR's own changes then meet `development`'s, and the squash does not re-conflict (#1401 on #1386, #1402 on #1382, 2026-10-24).

## What does git not catch?
- Before the chain, a review of each PR's files shared with `development` and the other PRs finds merges that are textually clean but wrong. 2026-10-23 examples:
  - a new design template that missed a token change;
  - test pins of the stock config column count that two schema PRs each moved;
  - a mail variable two PRs each left for the other to fill;
  - a plan finished by its last step but never deleted.
- Fix them on the PR that merges last.

## What happens to each PR before the merge?
- Blocking review findings are fixed on the branch, minor ones go to the follow-up issue (`merge-review.md`); the body is rewritten in English, the Claude Code footer removed (`pr-footer-and-trailers.md`), CI must be green on the final head, the squash commit keeps the PR title and gets a short English message, and the PR's own claim is closed with the PR number.
- Removing the footer is the merging session's job (owner, 2026-10-24): the sessions that opened the PRs need not touch it.

Related: docs/memory/workflow/git-branches-and-shared-files.md, docs/memory/workflow/owner-rules.md
