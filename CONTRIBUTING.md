# Contributing to Pinegrap CMS

Thank you for taking the time to contribute. This page is the short map; the
rules that bind a change are the ones listed below.

Issues and discussions may be written in Turkish or English. Code, comments,
commit messages and UI source strings are English; user-facing text reaches
the interface through `lang()` and is translated in `includes/local/tr.json`.

## Setting up

There is nothing to install. Pinegrap has no Composer step and no build
pipeline; third-party libraries are vendored under `pinegrap/includes/`.

- Reading, editing and running the static checks needs only PHP (7.0–8.5).
- A runnable instance, when you need to reproduce a runtime error, comes from
  one command: `bash tools/setup_sandbox.sh` (installs MariaDB, writes
  `data/config.php`, runs the installer, serves on `127.0.0.1:8000`). It is
  slow, so use it only when a code read does not answer the question.

The product lives under `pinegrap/`; paths in this page are relative to that
root unless they start with `tools/`.

## Workflow

1. Open or pick an issue first (see conventions below).
2. Branch off current `main`, one branch per topic
   (`fix/...`, `feature/...`, `docs/...`).
3. Commit in English with a clear, descriptive message.
4. Open a pull request against `main` and fill in the template. Draft PRs are
   welcome while work is in progress.

## Definition of done

A change is finished when all of the following hold:

- `php tools/lint.php` exits 0 (syntax check of the whole product tree).
- `php tools/check_lang.php` exits 0 (every `lang()` / `_sdT()` key exists in
  `tr.json`; `_sdT()` takes a single-quoted literal only).
- `php tools/check_bindings.php` exits 0 (every token the visual designer
  offers is produced by a renderer, and the other way round).
- `php tools/check_api_schema.php` exits 0 when the external API changed
  (every route declares what it answers with).
- New PHP/JS files start with the Pinegrap header block; new files under
  `includes/` start with their entry-constant gate (`PG_API_ENTRY`,
  `PG_ERP_ENTRY` or the one their siblings use).
- Schema changes go through the migration runner
  (`includes/migrations/runner.php` helpers, new line in `versions.php`),
  never a bare `ALTER TABLE`, and the step is re-runnable.
- `.src.js` and its `.min.js` twin are updated together.
- Comments are English and describe the code, not the process that produced
  it (no references to requests, phases, plans, sessions or conversations).
- The PR description states what changed, why, and what could not be
  verified (for example when no runnable instance was set up).

## Issue conventions

Title prefixes and labels used in this repository:

| Prefix | Label | Use |
|---|---|---|
| `[bug]` | `bug` | A defect with reproduction steps. |
| `[bug][runtime]` | `bug` | A defect reproduced on a running instance (fatal, exception, wrong output). |
| `[handoff:api]`, `[handoff:erp]`, `[handoff:security]` | `handoff:*` | A finding outside the filer's area, handed to the owner of that area. |
| `[karar]` | `karar` | A product decision is needed before work can start. |
| — | `enhancement` | A feature request. |
| — | `grup:*` | Working groups of an audit round; assigned by the maintainer. |

Issue forms under `.github/ISSUE_TEMPLATE/` set the prefix and label for you.
Security findings are **not** filed as public issues; see
[`SECURITY.md`](SECURITY.md).

## Working with AI agents

Several contributors to this repository are automated agents. The same rules
apply to them, plus these to keep parallel work from colliding:

- One issue per fix package, one branch per topic, always off current `main`.
- Before touching shared hot files (`includes/local/tr.json`,
  `includes/migrations/`, the change log) merge `main` into the branch again.
- Never force-push a branch someone else may have checked out.
- Before opening a PR, check open issues and PRs for the same `file:line`
  ranges; mention overlaps in the PR body.
- Findings outside the task's scope are handed off as issues
  (`[handoff:<area>]`), not fixed in passing.
- Decisions recorded in issues are applied as written. If one
  looks wrong, ask first (`[karar]`); never change it silently.
