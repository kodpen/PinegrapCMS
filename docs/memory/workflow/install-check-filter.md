---
name: install-check-filter
description: When install-check.yml runs its six-database matrix (#PRNUM): the changes job, the paths it reads, always on merge trains and pushes, why plain git, a skipped job blocks nothing
---

## When does the install check run its six databases?
- Its first job, `changes`, reads `git diff --name-only base...head` on a pull request; the matrix (`needs: changes`) runs when the diff touches `pinegrap/install/`, `pinegrap/includes/migrations/`, `tools/`, `pinegrap/data/backups/` (starter dumps) or `install-check.yml`, and always on a `merge-train/*` head and on pushes to main and development. A diff that cannot be read runs it. Before #PRNUM it ran on every pull request, docs-only ones included.
- Plain git, not a paths-filter action or a trigger-level `paths:`: a merge train must run whatever it changes. The draft rule sits on `changes`, so a skipped `changes` skips the matrix too.
- A skipped matrix job blocks nothing: `development` has no branch protection or ruleset requiring it (checked 2026-10-26). A required check added later (merge queue, `merge-flow-tools` step 6) must treat a skipped job as passed.

Related: docs/memory/workflow/ci-workflows.md, docs/memory/release/database-server-compatibility.md, docs/memory/plans/merge-flow-tools.md
