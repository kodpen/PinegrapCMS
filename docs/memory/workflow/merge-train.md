---
name: merge-train
description: Owner rule (2026-10-25) for the merging session: one merger, merge windows, one open train PR as the lock, building the train, one CI run, landing each PR by squash, changelog and bundles, a red train
---

## Why a train and not one PR at a time?
- One CI run takes about 20-25 minutes. Merging one PR, waiting for CI on the next, merging it caps the queue at two or three PRs an hour, and every single merge makes the PRs behind it conflict again. A train tests a batch once, and the PRs behind it see `development` move once per window instead of once per PR.

## Who merges?
- One merging session. A backup merges only when the owner says so, and then the first one stops. Two mergers shift `development` under each other: a train tests one base, and if another train lands first that test is wasted. An open `merge-train/*` PR is the lock: while one is open nobody builds another.

## When does the merging session merge?
- In windows: a scheduled routine wakes it every one to two hours. It does not react to each CI event in between. A security fix or a broken `development` may be merged at once, alone.

## Which PRs go into a train?
- Open PRs whose CI is green on their head and that are handed over ("Ready to merge"), or that have had no push for an hour while green. In dependency order (a PR that carries another's commits after it), then smaller first. About 5 to 10 per train. A PR over the size limit (about 500 changed lines, tests and memory aside) stays out: one comment asks for a split.

## How is the train built?
1. `git fetch origin && git checkout -b merge-train/<yyyymmdd-hhmm> origin/development`.
2. Merge each PR head in order. Settle the mechanical conflicts: language files with `php tools/tr_resolve.php --development=ours` ([[lang-files]]; until that tool is on `development`, by the `tr.json` rule in `pr-backlog-coordination.md`); generated indexes with `php tools/memory_index.php` (key-rules blocks by hand); `.min.js` bundles with `php tools/build_js.php`, never by hand (a one-line bundle such as `workspace.min.js` conflicts on every change); `docs/memory/core/fn-module-map-additions.md` keeps both lines.
3. Write `changelog.txt` from the PR bodies (the `[NEW]` lines, the `[SCHEME]` areas in the one `[SCHEME]` line): PRs do not edit it (`release/upgrade-changelog-rules.md`).
4. A conflict in logic, or a semantic clash found in the review, takes that PR out of the train (`merge-review.md`).
5. Push the train and open it as a draft PR to `development` (CI runs on pull requests only), titled "Merge train <time>: #a #b #c".

## How does the train land?
- Green: for each PR in train order, merge `development` into the PR branch with the same resolutions (the merge-base rule for stacked PRs in `pr-backlog-coordination.md` applies), push, and squash-merge it at once with its title and a short English message; no CI wait per PR, the train tested the result. After the last one, `git diff merge-train/<time> origin/development` must be empty; then close the train PR and each PR's claim.
- Red: find the PR that breaks it (drop the PRs that touch the failing area, or split the train in two), take it out with one comment naming the failure, rebuild the rest.

## Is GitHub's merge queue an option?
- It does the same (groups PRs, tests the merged result, squash-merges) but needs branch protection on `development` and a `merge_group:` trigger in `php-checks.yml`. `development` has no protection (2026-10-25), and adding a PR to the queue without GraphQL is untested; until then, the train above.

Related: docs/memory/workflow/merge-review.md, docs/memory/workflow/working-mode.md, docs/memory/workflow/pr-backlog-coordination.md
