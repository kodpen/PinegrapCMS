---
name: merge-train
description: Owner rule (2026-10-25) for the merging session: merge windows, building a train branch from the handed-over PRs, one CI run for the batch, landing each PR by squash, bisecting a red train
---

## Why a train and not one PR at a time?
- One CI run takes about 20-25 minutes. Merging one PR, waiting for CI on the next, merging it caps the queue at two or three PRs an hour, and every single merge makes the PRs behind it conflict again. A train tests a batch once, and the PRs behind it see `development` move once per window instead of once per PR.

## When does the merging session merge?
- In windows: a scheduled routine wakes it every one to two hours. It does not react to each CI event in between. A security fix or a broken `development` may be merged at once, alone.

## Which PRs go into a train?
- Open PRs whose CI is green on their head and that are handed over ("Ready to merge"), or that have had no push for an hour while green. In dependency order (a PR that carries another's commits after it), then smaller first. About 5 to 10 per train.

## How is the train built?
1. `git fetch origin && git checkout -b merge-train/<yyyymmdd-hhmm> origin/development`.
2. Merge each PR head in order. Settle the mechanical conflicts as `pr-backlog-coordination.md` says: `tr.json` (development's file plus the PR's added keys, `php tools/check_lang.php`), `changelog.txt`, the generated indexes (`php tools/memory_index.php`; key-rules blocks by hand), `docs/memory/core/fn-module-map-additions.md` (both lines).
3. A conflict in logic, or a semantic clash found in the review, takes that PR out of the train: one comment on the PR naming the conflict; its author or the next window settles it.
4. Push the train and open it as a draft PR to `development` (CI runs on pull requests only). Title "Merge train <time>: #a #b #c".

## How does the train land?
- Green: for each PR in train order, merge `development` into the PR branch with the same resolutions (the merge-base rule for stacked PRs in `pr-backlog-coordination.md` applies), push, and squash-merge it at once with its title and a short English message; no CI wait per PR, the train tested the result. After the last one, `git diff merge-train/<time> origin/development` must be empty; then close the train PR and each PR's claim.
- Red: find the PR that breaks it (drop the PRs that touch the failing area, or split the train in two), take it out with one comment naming the failure, rebuild the rest. The removed PR goes back to its author, who fixes it and hands it over again.

## What else does the merging session own?
- The review before merging, the PR body cleanup and footer removal (`pr-footer-and-trailers.md`), closing claims, and the plan steps a PR finishes when its author could not write the PR number (the author's PR text names them).
- Merging `development` into waiting PRs: only while building the train; never between windows.

## Is GitHub's merge queue an option?
- It does the same thing (groups PRs, tests the merged result, squash-merges) but needs branch protection on `development` and a `merge_group:` trigger in `php-checks.yml`. `development` has no protection today (checked 2026-10-25). Whether a cloud session can add a PR to the queue without GraphQL is not yet tested; until it is, the train above is the way.

Related: docs/memory/workflow/working-mode.md, docs/memory/workflow/pr-backlog-coordination.md
