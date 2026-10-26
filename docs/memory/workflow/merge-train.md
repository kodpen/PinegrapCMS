---
name: merge-train
description: Working rule (2026-10-25) for the merging session: one merger, one train at a time, 30-minute windows, rules-only PRs alone, the ready label, one merge commit per PR, one CI run
---

## Why a train and not one PR at a time?
- A CI run takes about ten minutes, more when runs queue. One PR at a time caps the queue at a few PRs an hour, and every merge makes the PRs behind it conflict again. A train tests a batch once, and `development` moves once per window instead of once per PR.

## Who merges?
- One merging session. A backup merges only when the owner says so, and then the first one stops. Two mergers shift `development` under each other: a train tests one base, and if another train lands first that test is wasted. An open `merge-train/*` PR is the lock: only the merger builds trains, the next one on the open train's tip ([[merger-wake]]).
- The merging session need not live long: the open train PR, the `ready` labels and this file carry its state.

## When does the merging session merge?
- As soon as a train lands, the next one is built from the ready, green PRs waiting: sessions wait on their merge for the next step, and a full queue stops new work. It wakes itself every 5 minutes and never waits on a CI event ([[merger-wake]]). A security fix or a broken `development` may be merged at once, alone.
- A PR that changes only `docs/`, `CLAUDE.md` or `.github/` lands alone as soon as it is green, outside the windows: every new session reads the rules from `development`, so a rules PR behind a train means sessions working under old rules. It gets its own `--no-ff` merge commit `<PR title> (#n)` on `origin/development` (indexes regenerated with `php tools/memory_index.php`), pushed fast-forward. When a train is already waiting and both regenerate the same index, it rides that train instead, so `development` does not move under the train.

## Which PRs go into a train?
- Open PRs whose CI is green on their head and that carry the `ready` label, or that have had no push for an hour while green. Smaller first, up to about 12 a train. A PR over the size limit (`working-mode.md`) goes into a train of its own or with small PRs: a red train is then easy to blame.

## How is the train built?
1. `git fetch origin && git checkout -b merge-train/<yyyymmdd-hhmm> origin/development`: from `origin`, never from a clone's local `development` (stale). `git config rerere.enabled true` once: a rebuild replays the resolutions.
2. For each PR in order: `git fetch origin refs/pull/<n>/head && git merge --no-ff --no-commit FETCH_HEAD`. Settle the mechanical conflicts: language files with `php tools/tr_resolve.php --development=ours` ([[lang-files]]), never a shard by hand; generated indexes with `php tools/memory_index.php` (key-rules blocks by hand); `.min.js` bundles with `php tools/build_js.php`, never by hand; `docs/memory/core/fn-module-map-additions.md` keeps both lines; a plan file keeps both step lines, in the form of the `plans/README.md` key rules. Write the PR's `changelog.txt` line from the Changelog section of its body (`release/upgrade-changelog-rules.md`). Then `git commit -m "<PR title> (#<n>)"`: one merge commit per PR, with its title, number and changelog line.
3. A PR whose branch commits carry a trailer or an author other than `Erdal Güral` goes back to its session to fix the branch (`git-branches-and-shared-files.md`). A bot PR (Dependabot) is the one exception: taken with `git merge --squash FETCH_HEAD`, committed the same way and closed with a comment naming the landing commit, since GitHub will not mark it merged.
4. A conflict in logic, or a semantic clash found in the review, takes that PR out of the train (`merge-review.md`).
5. Push the train and open it as a draft PR to `development` (a pushed branch runs no CI, and of the drafts only `merge-train/*` heads get it), titled "Merge train <time>: #a #b #c". Then [[merge-train-landing]].

Related: docs/memory/workflow/merge-train-landing.md, docs/memory/workflow/merge-review.md, docs/memory/workflow/session-lifecycle.md
