---
name: pr-backlog-coordination
description: The one session that merges PRs on development: which PRs it may push to, merge order, tr.json and changelog conflicts in a train, stacked PRs, semantic conflicts, English bodies
---

## Who merges PRs into development?
- Owner (2026-10-24): about ten sessions open PRs and one session merges them all; the others do not merge. Each round it lists the open PRs (head, `mergeable_state`, CI on the head), reviews each one once and fixes only blocking findings on the branch (`merge-review.md`), cleans body and comments, merges in a train (`merge-train.md`) and closes any claim of the PR still open.

## Which PRs may the merging session push to?
- A handed-over PR ("Ready to merge") or one with no push for an hour while green (`merge-train.md`); before that the branch belongs to the session pushing to it.
- No other session merges (owner, 2026-10-25: `working-mode.md`); the merging session re-checks a PR's merge state right before each merge, never trusting an earlier "clean".

## In which order do they merge?
- A PR that carries another open PR's commits waits for it; the PR that rewrites the memory tree merges last.
- `tr.json` keys are appended at the end of the file, and the last key's comma changes with each append, so every two `tr.json` PRs conflict. They go into one merge train together and are settled there (`merge-train.md`), until the split in `docs/memory/plans/tr-json-split.md` lands.
- A language-file conflict: `php tools/tr_resolve.php --development=ours` in the train, `php tools/tr_resolve.php` when merging `development` into a branch, then `php tools/check_lang.php` ([[lang-files]]). `changelog.txt` is not in PRs: the merging session writes the lines named in the PR bodies (`merge-train.md`).
- Generated indexes (`docs/memory/README.md`, folder READMEs) are regenerated with `php tools/memory_index.php`, but the key-rules block between the markers is hand-written: keep the lines both sides added. Taking one side's README whole drops the other side's key rule (#1382 lost one this way, restored before merging).

## How is a stacked PR brought up to date after its base PR was squash-merged?
- Not by rebasing someone else's branch. First merge the base PR's final head into it (a normal merge). Then merge `development` with that head as the merge base: `git merge-tree --write-tree --merge-base=<base PR head> HEAD origin/development`, then `git commit-tree <tree> -p HEAD -p origin/development`. Only the stacked PR's own changes then meet `development`'s, and the squash does not re-conflict (#1401 on #1386, #1402 on #1382, 2026-10-24).

## What does git not catch?
- Before the train, a review of each PR's files shared with `development` and the other PRs finds merges that are textually clean but wrong. 2026-10-23 examples:
  - a new design template that missed a token change;
  - test pins of the stock config column count that two schema PRs each moved;
  - a mail variable two PRs each left for the other to fill;
  - a plan finished by its last step but never deleted.
- Fix them on the PR that merges last.

## What happens to each PR before the merge?
- Blocking review findings are fixed on the branch, minor ones go to the follow-up issue (`merge-review.md`); the body is rewritten in English, the Claude Code footer removed (`pr-footer-and-trailers.md`), CI must be green on the final head, the squash commit keeps the PR title and gets a short English message, and the PR's claim, if still open, is closed with the PR number.
- Removing the footer is the merging session's job (owner, 2026-10-24): the sessions that opened the PRs need not touch it.

Related: docs/memory/workflow/git-branches-and-shared-files.md, docs/memory/workflow/owner-rules.md
