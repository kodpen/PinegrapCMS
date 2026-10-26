---
name: pre-push-checks
description: git push runs tools/pre_push.sh first (installed when a cloud session starts in the clone): which CI checks it runs on the pushed commit, why, installing it elsewhere, its blind spots, skipping
---

## Why does a push run checks?
- A failed workflow run on a pull request branch sends a "Run failed" e-mail to the account that pushed, and cloud sessions push under the owner's account. From 2026-10-21 08:00 to 2026-10-23 06:00 (UTC) 16 runs failed on PR branches: 7 at `tools/memory_index.php --check` (an index not regenerated, a file over 4096 bytes, a description over 200 characters; often a memory file edited after the PR went up, for its PR number), 4 in the unit tests, 3 from one parse error (PHP checks, the install check and the Docker check), 1 PHPCompatibility finding, 1 pecl.php.net outage. 12 of them fail on the pushed commit in a session too.

## What does it run, and when?
- In a temporary worktree of the commit being pushed (about a second to make; edits, ignored files and a sparse checkout do not count). What runs follows from what the branch changes since it left `origin/development`, as CI checks the whole pull request:
  - every push: the memory index and, with a base, the branch's commits and changelog (`tools/check_pr.php`, `branch-guards.md`);
  - any change outside `docs/`, `.github/`, `.claude/`, the root `*.md` files and `license.txt`: `php -l` of the changed PHP files, `check_lang`, `check_bindings`, `check_api_schema`, `check_copies`, the unit tests;
  - a change under `pinegrap/assets/js/`, to `pg_js_bundles()`'s file or to `tools/build_js.php`: `node --check` of the three panel scripts CI checks and `tools/build_js.php --check`; skipped with a note when node, npx or the npm registry is missing.
- A documentation-only push takes under two seconds, a code push 25-40 (the unit tests take 20-30). Without `origin/development` it runs everything, the whole-tree `tools/lint.php` included (about 75 seconds). Deletions and tags are not checked.
- CI alone runs PHPStan and PHPCompatibility (minutes), the code editor bundle (`npm ci`), the install check and the Docker check. A fast step added to `php-checks.yml` is added to the script too.

## What can it not see?
- A failure that exists only in the merge with `development` (an index or a test broken by what development gained meanwhile), a test that fails only on CI's newer PHP patch release (two GD runs in that period), and anything written to a branch without `git push`: the GitHub contents API, MCP `push_files`/`create_or_update_file`, the web editor, "Update branch".

## How is it installed?
- `.claude/settings.json` has a `SessionStart` hook that runs `tools/install_git_hooks.sh --session-start`. In a cloud session (`CLAUDE_CODE_REMOTE=true`) that starts inside this clone, on a branch that carries the script, it writes a shim to the clone's `pre-push` hook (shared by every worktree), which runs the `tools/pre_push.sh` of the worktree a push starts from, and it prints the line "git push runs tools/pre_push.sh first …" into the session's context. A checkout without the script (main, release branches) pushes unchecked.
- Sessions that start elsewhere (several repositories, one added later) and the owner's machine run `bash tools/install_git_hooks.sh` once. A `pre-push` hook that is not the shim, or `core.hooksPath`, is left alone; the installer prints a guarded line to put first in that hook that passes the refs on.
- By hand: `bash tools/pre_push.sh` checks HEAD the same way, `bash tools/pre_push.sh <base>` against another base.

## May a push skip it?
- Not to push a commit that fails: fix it and push again. `git push --no-verify` only when the script itself is broken (say so in the PR), and fix the script.

Related: docs/memory/workflow/ci-workflows.md, docs/memory/workflow/memory-index-key-rules.md
