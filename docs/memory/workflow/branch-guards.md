---
name: branch-guards
description: tools/check_pr.php (#1878): CI and pre-push fail a commit trailer, an author or committer other than Erdal Güral, a changelog.txt edit; warn above 1,000 product lines
---

## What does it check?
- On the commits of a branch that `origin/development` does not reach: no `Co-Authored-By:` or `Claude-Session:` line (any case, at the start of a line), author `Erdal Güral <erdaltyy@gmail.com>` (name and address exactly), committer the same or `GitHub <noreply@github.com>`.
- On the change since the merge base: no edit to `pinegrap/changelog.txt` (`working-mode.md`); above 1,000 product lines (under `pinegrap/`, `.min.js` and `includes/local/` not counted) a warning annotation, never a failure (`working-mode.md`, PR size).
- Why: on 2026-10-26 a PR was sent back because a merge commit carried Claude's default author; the rules were text only and broke (`plans/merge-flow-tools.md`).

## Where does it run?
- CI: the `guards` job of `php-checks.yml`, on pull requests to `development` that are ready (a merge train as a draft too), with the full history (`fetch-depth: 0`). Failures are error annotations.
- Before a push: `tools/pre_push.sh` runs it on every push that has a base (`pre-push-checks.md`), the branch taken from the pushed ref.
- By hand: `php tools/check_pr.php origin/development HEAD --branch=<name>`.

## Who skips what?
- `merge-train/*`, `development` and `main` skip the author, changelog and size checks: they carry other branches' commits and the merging session writes the changelog. A pull request opened by `dependabot[bot]` (`--identity-exempt`, set by the job from the PR's user, never from the branch name) skips the same. The trailer check applies everywhere.

## Why is GitHub allowed as committer?
- GitHub commits as `GitHub <noreply@github.com>` whatever it makes for the signed-in account: a "Merge branch 'development' into …" from the web UI or "Update branch", the web editor, the contents API (MCP `push_files`). The author of such a commit is the account's own identity and is judged like any other, so a web merge by an account whose identity is not Erdal Güral still fails; only the committer is let through.

## Why does it quote git's arguments itself on Windows?
- `check_pr_git()` starts git without cmd.exe (`bypass_shell`), but Windows `escapeshellarg()` turns `%`, `!` and `"` into spaces for cmd.exe: git got `--format= H x1f an …` and failed with `invalid --pretty format`, so every push from Windows stopped in the hook (#2086 went up with `--no-verify`). Since #2091 `check_pr_windows_arg()` quotes as git's C runtime splits a command line; Linux keeps `escapeshellarg()`. The argument-list form of `proc_open()` would do it too, but needs PHP 7.4.

## How is a failing branch fixed?
- A wrong author on your own unpushed commits: `git config user.name/user.email`, then `git rebase --exec 'git commit --amend --no-edit --reset-author' origin/development` (a trailer: amend the message). On a pushed branch nobody else uses, the same and a `--force-with-lease` push.
- A `changelog.txt` edit: revert it on the branch and name the line in the PR body.

Related: docs/memory/workflow/pre-push-checks.md, docs/memory/workflow/pr-footer-and-trailers.md, docs/memory/workflow/ci-workflows.md
