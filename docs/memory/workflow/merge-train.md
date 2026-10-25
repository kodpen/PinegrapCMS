---
name: merge-train
description: Owner rule (2026-10-25) for the merging session: one merger, one train at a time, merge windows, rules-only PRs alone, the ready label, one merge commit per PR, one CI run
---

## Why a train and not one PR at a time?
- A CI run takes about ten minutes of runner time, more when runs queue. Merging one PR, waiting for CI on the next, merging it caps the queue at a few PRs an hour, and every single merge makes the PRs behind it conflict again. A train tests a batch once, and the PRs behind it see `development` move once per window instead of once per PR.

## Who merges?
- One merging session. A backup merges only when the owner says so, and then the first one stops. Two mergers shift `development` under each other: a train tests one base, and if another train lands first that test is wasted. An open `merge-train/*` PR is the lock: while one is open nobody builds another, and no train is built on an open train (2026-10-25: four stacked trains all went `dirty` when the first landed).
- The merging session need not live long: a routine may start a fresh one per window; the open train PR, the `ready` labels and this file carry its state.

## When does the merging session merge?
- In windows: a scheduled routine wakes it every one to two hours. It does not react to each CI event in between. A security fix or a broken `development` may be merged at once, alone.
- A PR that changes only `docs/`, `CLAUDE.md` or `.github/` lands alone as soon as it is green, outside the windows: every new session reads the rules from `development`, so a rules PR behind a train means sessions working under old rules. Merge `origin/development` into it if needed, `php tools/memory_index.php` for the indexes, and merge it.

## Which PRs go into a train?
- Open PRs whose CI is green on their head and that carry the `ready` label (`gh api 'repos/kodpen/pinegrapcms/issues?state=open&labels=ready&per_page=100' --jq '.[]|select(.pull_request)|.number'`), or that have had no push for an hour while green. In dependency order (a PR that carries another's commits after it), then smaller first. About 5 to 10 per train. A PR over the size limit (`working-mode.md`) goes into a train of its own or with small PRs: a red train is then easy to blame.

## How is the train built?
1. `git fetch origin && git checkout -b merge-train/<yyyymmdd-hhmm> origin/development`: from `origin`, never from a clone's local `development` (stale). `git config rerere.enabled true` once: a rebuild replays the resolutions.
2. For each PR in order: `git fetch origin refs/pull/<n>/head && git merge --no-ff --no-commit FETCH_HEAD`. Settle the mechanical conflicts: language files with `php tools/tr_resolve.php --development=ours` ([[lang-files]]); generated indexes with `php tools/memory_index.php` (key-rules blocks by hand); `.min.js` bundles with `php tools/build_js.php`, never by hand; `docs/memory/core/fn-module-map-additions.md` keeps both lines. Write the PR's `changelog.txt` line from the Changelog section of its body (`release/upgrade-changelog-rules.md`). Then `git commit -m "<PR title> (#<n>)"`: one merge commit per PR, with its title, number and changelog line.
3. A PR whose branch commits carry a trailer or an author other than `Erdal Güral` is taken with `git merge --squash FETCH_HEAD` and committed the same way; GitHub will not mark it merged, so it is closed with a comment naming the landing commit.
4. A conflict in logic, or a semantic clash found in the review, takes that PR out of the train (`merge-review.md`).
5. Push the train and open it as a draft PR to `development` (CI runs on pull requests only), titled "Merge train <time>: #a #b #c". Then [[merge-train-landing]].

Related: docs/memory/workflow/merge-train-landing.md, docs/memory/workflow/merge-review.md, docs/memory/workflow/working-mode.md
