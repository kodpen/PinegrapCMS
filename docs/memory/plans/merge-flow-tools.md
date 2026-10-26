---
name: merge-flow-tools
description: Plan (2026-10-25, steps 2, 3 and 5 done): merge_train.php (lock, build, fast-forward landing), CI guards, filtered install matrix, generated fn-module map, lang cache, merge queue
---

## Why?
- The working mode was written as text on 2026-10-25 and broken the same day, by the merging session as much as by the others: four trains stacked on each other, PRs of 8,000 lines in a train, 26 open PRs against a limit of 10. Nothing checked the rules. `tools/pre_push.sh` showed that a check holds where text does not. Numbers behind the limits: of 60 PRs opened on 2026-10-24/25, half changed over 500 product lines and a third over 1,000; 34 touched `tr.json`, 7 `changelog.txt`, 11 a `.min.js` bundle.

## What was decided (2026-10-25)?
- A green train lands by one fast-forward push (`workflow/merge-train-landing.md`); a PR is handed over with the `ready` label; the PR size is about 1,000 product lines; the queue limit is 15 non-draft, non-train PRs, checked before the draft PR that claims a task; a PR of rules and memory only lands alone at once (`workflow/working-mode.md`); no issues between sessions, a claim is a draft PR on which CI does not run (`CLAUDE.md`).

## What are the steps (one PR each)?
1. `tools/merge_train.php`: `--start` refuses while an open `merge-train/*` PR exists (the lock); `--list` prints the eligible PRs (green head, `ready` label or quiet for an hour, product lines) in train order; `--build` fetches `refs/pull/<n>/head`, merges each with `--no-ff --no-commit`, runs `tr_resolve.php`, `memory_index.php` and `build_js.php` on conflicts and writes `changelog.txt` from the Changelog section of each body; `--land` checks `merge-base --is-ancestor` and pushes fast-forward. The pure parts (train order, the Changelog section parser, the product-line count) in `includes/`-free `tools/` code with `tests/merge_train_test.php`.
2. CI guards in `php-checks.yml`: a PR whose head is not `merge-train/*` fails when it changes `pinegrap/changelog.txt`; a commit on the PR with a `Co-Authored-By:` or `Claude-Session:` trailer or an author other than `Erdal Güral` fails; more than 1,000 product lines prints a warning annotation, never a failure. `tools/pre_push.sh` gets the trailer and author check too. Done in #1878 (`workflow/branch-guards.md`).
3. `install-check.yml` runs its six-database matrix only when `pinegrap/install/**`, the migration files or `tools/` change, and always on a `merge-train/*` head; Done in #1985 (`workflow/ci-workflows.md`).
4. `docs/memory/core/fn-module-map-additions.md` is append-only like the old `tr.json` (8 commits in three days): generate it with `tools/memory_index.php` from a `module:` frontmatter field of the memory files, and drop the hand-written file.
5. A compiled language cache: `lang()` decodes about 2.8 MB of JSON on every request, from one file or from the shards alike (the shards about 20 ms, the old single file 16 ms: [[lang-files]]). Write `data/temp/lang_<code>.php` (an array, kept by opcache) from the language files, rebuilt when their mtime changes. Independent of the split. Done in #1933 (`core/lang-compiled-cache.md`).
6. GitHub's merge queue, now that the Turkish wording is in sorted shards ([[lang-files]]): rulesets on `development`, a `merge_group:` trigger in `php-checks.yml`, auto-merge (`mcp__github__enable_pr_auto_merge`) as the way into the queue from a cloud session. It replaces the build and landing of step 1.

## What is still open?
- Whether the merging session is a fresh session per window (a routine that creates a session on each firing); the lock makes it safe either way.

## Where to start?
- Step 1: a draft PR titled `[plan:merge-flow-tools] 1 — merge_train.php` claims it. Step 4 is independent of it.
