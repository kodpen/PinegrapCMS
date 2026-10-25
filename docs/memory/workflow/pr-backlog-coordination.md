---
name: pr-backlog-coordination
description: The one session that merges PRs on development: which PRs it may push to, merge order, tr.json and changelog conflicts, a PR on another PR's commits, semantic conflicts, English bodies
---

## Who merges PRs into development?
- About ten sessions open PRs and one session merges them all; the others do not merge. Each round it lists the open PRs (head, `mergeable_state`, CI on the head), reviews each one once and fixes only blocking findings on the branch (`merge-review.md`), cleans body and comments and merges in a train (`merge-train.md`).

## Which PRs may the merging session push to?
- A handed-over PR (the `ready` label) or one with no push for an hour while green (`merge-train.md`); before that the branch belongs to the session pushing to it.
- No other session merges (`working-mode.md`); the merging session re-checks a PR's merge state right before each merge, never trusting an earlier "clean".

## In which order do they merge?
- A PR that carries another open PR's commits waits for it; the PR that rewrites the memory tree merges last.
- `tr.json` keys are appended at the end of the file, and the last key's comma changes with each append, so every two `tr.json` PRs conflict. They go into one merge train together and are settled there (`merge-train.md`), until the split in `docs/memory/plans/tr-json-split.md` lands.
- A `tr.json` conflict: `php tools/tr_resolve.php --development=ours` (`core/lang-files.md`) does this: `development`'s file plus the keys the PR added, the PR's value for a key it changed; then `php tools/check_lang.php` (no key twice). `changelog.txt` is written by the merging session from the PR body (`merge-train.md`).
- Generated indexes (`docs/memory/README.md`, folder READMEs) are regenerated with `php tools/memory_index.php`, but the key-rules block between the markers is hand-written: keep the lines both sides added. Taking one side's README whole drops the other side's key rule (#1382 lost one this way, restored before merging).

## How is a PR that carries another PR's commits brought up to date?
- Not by rebasing someone else's branch: merge `origin/development` into it. A train lands by fast-forward (`merge-train-landing.md`), so the base PR's commits are in `development` unchanged and the merge is clean. Was the base PR squash-merged (before 2026-10-25, or with `--squash`)? Merge its final head first, then `development` with that head as the merge base: `git merge-tree --write-tree --merge-base=<base PR head> HEAD origin/development`, then `git commit-tree <tree> -p HEAD -p origin/development` (#1401 on #1386, 2026-10-24).

## What does git not catch?
- Before the train, a review of each PR's files shared with `development` and the other PRs finds merges that are textually clean but wrong. 2026-10-23 examples:
  - a new design template that missed a token change;
  - test pins of the stock config column count that two schema PRs each moved;
  - a mail variable two PRs each left for the other to fill;
  - a plan finished by its last step but never deleted.
- Fix them on the PR that merges last.

## What happens to each PR before the merge?
- Blocking review findings are fixed on the branch, minor ones are left (`merge-review.md`); the body is rewritten in English, the Claude Code footer removed (`pr-footer-and-trailers.md`), CI must be green on the final head, and its merge commit in the train carries the PR title and number (`merge-train.md`).
- Removing the footer is the merging session's job: the sessions that opened the PRs need not touch it.

Related: docs/memory/workflow/git-branches-and-shared-files.md, docs/memory/workflow/owner-rules.md
