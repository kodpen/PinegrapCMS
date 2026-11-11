---
name: memory-long-lines
description: The memory index tool refuses a memory line over 500 characters that a branch adds or rewrites; old lines pass; the base from pre_push, CI (PR base or push before) or merge-base; no base
---

## What does the tool refuse?
- `tools/memory_index.php` (every run, `--check` included) refuses a line over 500 characters (`MAX_LINE_CHARS`; characters, not bytes) that the branch adds or rewrites in a file under `docs/memory/`. The error names the file, the line number, the length and the line's start (#8734, step MR1 of `plans/memory-rule-checks.md`).
- The rule itself and why (the search tool hides a longer line): [[memory-writing]].
- A folder README counts only its hand-written key rules block (`memory_index_readme_long_lines()`); the generated indexes are not read.

## Why do the old long lines pass?
- Architect (2026-11-11): the 1,299 long lines of 2026-11-11 are not swept in one go: editing 910 files under the open PRs of about fifteen sessions costs more in conflicts than the lines cost in searches. A PR that edits a file splits that file's other long lines.
- `memory_index_long_lines($content, $base)` (pure, `tests/memory_index_test.php`): a line standing word for word in the same file at the base passes wherever it now stands; a copy beyond the base's count is new. A new or renamed file has no base: every long line counts.
- Only files changed since the base are read (`git diff --name-only` plus untracked files), each base file by `git show <base>:<path>`.

## Where does the base come from?
- `--base=<commit>`, else `git merge-base HEAD origin/development` (a run by hand).
- `git push`: `tools/pre_push.sh` passes the commit where the pushed branch left development (`fork_point`), in the pushed commit's worktree.
- CI (`php-checks.yml`, job `checks`, step "Memory index matches its files"): the checkout is one commit deep, so the step fetches the base first: the pull request's base commit, or on a push to development the branch head before the push (`github.event.before`). A base that cannot be fetched (a new branch's zeros) fails the step.

## What if there is no base?
- No git, no `origin/development` or a shallow clone without the fork point: `--check` fails ("the long-line check cannot read the base", with `git fetch origin development` or `--base=` as the remedy); a plain run warns on stderr and writes the indexes. The rule never passes in silence.
- A merge train runs the plain tool after a merge: the base is where the train left development.
