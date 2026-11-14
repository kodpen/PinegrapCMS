---
name: merge-train-tool
description: tools/merge_train.php (#2002): --start (the lock), --list (train order), --build (merge, settle, changelog, push), --land (fast-forward); what it leaves to the merging session
---

## What does each mode do?
- `--start`: refuses while an open `merge-train/*` PR exists (the lock), naming it; `--stack` starts on the tip of the newest open train instead ([[merger-wake]]). Creates `merge-train/<yyyymmdd-hhmm>` (UTC) from `origin/<ref>`, turns `rerere` on, records the start in `branch.<name>.mergeTrainBase`.
- `--list`: open, non-draft PRs to `development`, not from a fork ([[merge-train-forks]]), that no open train carries (its title's `#n` and its merge commits' `(#n)`), green on their head and labelled `ready` or quiet for an hour (the first check run's start). Printed as docs-only PRs landing alone, the train and a later train, in the order and limits of [[merge-train-order]].
  - Green: every check run completed as success, neutral or skipped, but the channel report jobs of [[merge-train]], which move no time either.
- `--build [<n> …] [--no-push]`: on the current train branch (clean tree, no merge open, identity Erdal Güral), each PR (the `--list` train when none is named): `git fetch origin refs/pull/<n>/head`, skipped when already in the branch, `git merge --no-ff --no-commit`, conflicts settled, changelog written, `git commit -m "<title> (#n)"`.
  - After the last one, `php tools/memory_index.php`, a change committed as "Regenerate the memory indexes", then `build_js.php` and `build_css.php` as "Rebuild the bundles" (2963), each only on a change: PRs carry neither (`git-branches-and-shared-files.md`); the train's CI runs `--check`. Then `git push -u origin <train>` and the draft PR "Merge train <time>: #a #b" opened, or retitled.
- `--indexes`: the same two commits on the branch of a PR landing alone, before its fast-forward (2854).
- `--land`: HEAD must be the pushed `origin/<train>`, `origin/development` its ancestor and its check runs green; then `git push origin <sha>:refs/heads/development`, a fast-forward ([[merge-train-landing]]).

## Which conflicts does it settle?
- Language files: `php tools/tr_resolve.php --development=ours`. Memory `README.md` and `INDEX.md` indexes, `workflow/DECISIONS.md` and `core/fn-modules.md`: `php tools/memory_index.php`, only when no other memory file is in conflict. `.min.js` / `.min.css`: `php tools/build_js.php` / `build_css.php`, only when no `.js` / `.css` is in conflict. A path is staged once no marker is left.
- Anything else (logic, a plan file, a key-rules block) stops the run, the merge left open: settle and `git commit -m "<title> (#n)"`, or `git merge --abort` to take the PR out (`merge-review.md`), then `--build` with the PRs left.

## How is changelog.txt written?
- From the `## Changelog` section of the PR body (any heading level, up to the next heading): every `[NEW] Added …` (up to a backtick or the line end) goes after the open version's `[NEW]` lines; a mention of `[FIX]` or `[SECURITY]` adds that catch-all line once; a section starting with "none" adds only those. Order kept: `[NEW]`, `[FIX]`, `[SECURITY]`, `[SCHEME]` (`release/upgrade-changelog-rules.md`).
- A `[SCHEME]` item stops the run with the merge open: the version's one `[SCHEME]` sentence is widened by hand, then commit and `--build` again. Only an item under a `Changelog` heading stops it (#2095): a train touching `includes/migrations/` is checked for the sentence before CI.

## What does it not do?
- Review (`merge-review.md`), the PR body cleanup (`pr-footer-and-trailers.md`), a bot PR's `--squash` merge, landing a docs-only PR alone ([[merge-train]]). It pushes only its train and, in `--land`, `development`, through `gh api`.
- The pure parts (order, eligibility, product lines, the changelog parser and writer, the union of a conflict, Windows quoting: `merge_train_windows_arg()`, #2193, as `escapeshellarg()` there blanks `%`, `!` and `"`) are tested in `tests/merge_train_test.php`, product lines and quoting against `check_pr.php` (`branch-guards.md`).
