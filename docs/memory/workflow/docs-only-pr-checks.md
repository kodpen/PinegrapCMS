---
name: docs-only-pr-checks
description: A pull request whose every changed file is under docs/ runs only the memory check and the branch guards in php-checks.yml; its jobs keep their names and end green
---

## What does a docs-only pull request run?
- In `php-checks.yml`, when every changed file of a pull request is under `docs/`, the `checks` job runs only the memory check (`php tools/memory_index.php --files`) and `guards` runs in full; every other step of `checks` and of `php71` is skipped.
- Each job keeps its name and ends green, so the check names the merging session reads as green (`merge-train.md`) stay the same and the agent channel's `[ci]` line still comes.

## What runs everything?
- A pull request that changes any other path, `CLAUDE.md` and `.github/` included; a merge train (`merge-train/*`); and every push to `development` or `main`.

## How is a pull request found docs-only?
- The step "Changed paths" (id `scope`) in `checks` and `php71` diffs the pull request's merge commit against its first parent (checkout `fetch-depth: 2`), with `--no-renames` so a file moved out of or into `docs/` counts both paths. An empty diff or a failed diff runs everything.
- A step added to either job later gets `steps.scope.outputs.docs_only != 'true'` in its `if:` unless a docs-only pull request needs it too.

## Why?
- A one-line decision or plan PR ran every job, ten to thirty minutes, before it could land (`plans/plan-state-channel.md`, PS1).

Related: docs/memory/workflow/ci-workflows.md, docs/memory/workflow/merge-train.md
