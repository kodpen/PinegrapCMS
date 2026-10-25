---
name: merge-train-landing
description: How a green merge train lands (2026-10-25): one fast-forward push, GitHub marks the PRs merged, no push to PR branches, no second CI run; why not a squash per PR; a red train; the merge queue
---

## How does a green train land?
- `git fetch origin development`; `origin/development` must be an ancestor of the train (`git merge-base --is-ancestor origin/development merge-train/<time>`), else merge it in and run CI again.
- One push: `git push origin merge-train/<time>:development`, a fast-forward; `tools/pre_push.sh` runs on the tip. GitHub marks every PR whose head commit is now reachable from `development` as merged and closes it, the train PR included, which wakes each PR's session for its next step (`working-mode.md`); nothing is pushed to a PR branch and no CI runs again. A PR taken with `--squash` (`merge-train.md`) is closed by hand with a comment naming its commit.
- `git log --first-parent development` reads one line per PR, `<PR title> (#n)`, as the squash log did; the PR's own commits sit below its merge commit.

## Why not a squash merge per PR after the train?
- The squash commits re-create the train's content with other commits: every PR or train built on the train went `dirty` when the first PRs landed (2026-10-25: #1644 after three PRs of #1636), each PR branch got one more push and one more CI run of eight jobs, and the train's resolutions had to be reproduced by hand (hence the old "`git diff` train against `development` must be empty"). The fast-forward lands exactly what CI tested, in one push.
- A PR's branch commits therefore reach `development`: they are written as `Erdal Güral`, without trailers (`git-branches-and-shared-files.md`, `pr-footer-and-trailers.md`).

## What if the train is red?
- Find the PR that breaks it: drop the PRs that touch the failing area, or split the train in two. Take it out with one comment naming the failure, rebuild the rest from `origin/development` (rerere replays the resolutions) and run CI once more. Nothing lands before the train is green: a red `development` makes every PR's CI red for the train's reason.

## Is GitHub's merge queue an option?
- It does the same (groups PRs, tests the merged result, merges) but needs rulesets on `development` and a `merge_group:` trigger in `php-checks.yml`, and it cannot settle a language-file conflict. With the Turkish wording in sorted shards ([[lang-files]]) it is the next step (plan [[merge-flow-tools]]); until then, the train.

Related: docs/memory/workflow/merge-train.md, docs/memory/workflow/merge-review.md
