---
name: merge-train-tool
description: tools/merge_train.php (#2002): --start (the lock), --list (train order), --build (merge, settle, changelog, push), --land (fast-forward); what it leaves to the merging session
---

## What does each mode do?
- `--start`: refuses while an open `merge-train/*` PR exists (the lock), naming it; `--stack` starts on the tip of the newest open train instead ([[merger-wake]]). Creates `merge-train/<yyyymmdd-hhmm>` (UTC) from `origin/<ref>`, turns `rerere` on and records the start commit in `branch.<name>.mergeTrainBase`.
- `--list`: open, non-draft PRs to `development` that no open train carries (its title's `#n` and its merge commits' `(#n)`), green on their head (every check run completed as success, neutral or skipped) and labelled `ready` or quiet for an hour (the first check run's start, not the commit date). Printed as: docs-only PRs that land alone, the train (within 1,000 product lines, smaller first, at most 12), a later train (the rest; an oversized PR rides alone).
- `--build [<n> …] [--no-push]`: on the current train branch (clean tree, no merge open, git identity Erdal Güral), each PR (the `--list` train when none is named): `git fetch origin refs/pull/<n>/head`, skipped when already in the branch, `git merge --no-ff --no-commit`, conflicts settled, changelog written, `git commit -m "<title> (#n)"`. Then `git push -u origin <train>` and the draft PR "Merge train <time>: #a #b" opened, or retitled.
- `--land`: HEAD must be the pushed `origin/<train>`, `origin/development` its ancestor and its check runs green; then `git push origin <sha>:refs/heads/development`, a fast-forward ([[merge-train-landing]]).

## Which conflicts does it settle?
- Language files: `php tools/tr_resolve.php --development=ours`. Memory `README.md` indexes and `docs/memory/core/fn-modules.md`: `php tools/memory_index.php`, only when no other memory file is in conflict. `.min.js` bundles: `php tools/build_js.php`, only when no `.js` source is in conflict. A path is staged only when no conflict marker is left.
- Anything else (logic, a plan file, a key-rules block) stops the run with the merge open and the commands to go on: settle and `git commit -m "<title> (#n)"`, or `git merge --abort` to take the PR out (`merge-review.md`), then `--build` with the PRs left. A PR already in the branch is skipped, so the same command resumes.

## How is changelog.txt written?
- From the `## Changelog` section of the PR body (any heading level, up to the next heading): every `[NEW] Added …` (up to a backtick or the line end) goes after the open version's `[NEW]` lines; a mention of `[FIX]` or `[SECURITY]` adds that catch-all line once; a section starting with "none" adds only those. Order kept: `[NEW]`, `[FIX]`, `[SECURITY]`, `[SCHEME]` (`release/upgrade-changelog-rules.md`).
- A `[SCHEME]` item stops the run with the merge open: the version's one `[SCHEME]` sentence is widened by hand, then commit and `--build` again. Only an item under a `Changelog` heading stops it: a schema PR that names its item elsewhere in the body (#2095) went through with no `[SCHEME]` change, so a train whose diff touches `includes/migrations/` is checked for the sentence before CI.

## What does it not do?
- Review (`merge-review.md`), the PR body cleanup and footer (`pr-footer-and-trailers.md`), a bot PR's `--squash` merge and closing comment, landing a docs-only PR alone ([[merge-train]]). It pushes only its train branch and, in `--land`, `development`; GitHub goes through `gh api` (REST).
- The pure parts (order, eligibility, product lines, the changelog parser and writer, the union of a conflict) are tested in `tests/merge_train_test.php`; the product-line count is held equal to `check_pr_product_lines()` (`branch-guards.md`).

Related: docs/memory/workflow/merge-train.md, docs/memory/workflow/merge-train-landing.md, docs/memory/workflow/merger-wake.md
