---
name: open-version-schema-census
description: Plan (owner 2026-11-19): find the tables, columns and indexes 2026.11.0 added that no code uses any more and take them out where they were added; SC1 the census, SC2 the removals
---

## What did the owner ask?
- Owner (2026-11-19, `workflow/decisions-made-schema-28.md`): "bu konu atlanmasın db gereksiz şişmesin (eski sürümlerde bu hata sıkça yapıldı)". Schema the open version added and then gave up is removed where it was added; the rule and how a removal is written: `release/released-vs-open-version.md`.

## What is built?
- SC1, the census: one worker, one docs-only PR, nothing removed. It reads the open version's migration file (`release/release-status.md` names it) sub-step by sub-step and lists every table, column and index the version adds, each with its step label and the PR that added it. Done: PR #10391 (`release/open-version-schema-census-list.md`).
- For each it says whether code under `pinegrap/` still reads or writes it: the name searched in PHP and JavaScript, and a name held only by its own step, `get_tables()` and tests counts as unused. It also lists what the same version replaced: two columns for one value, a column and a table for one record, a table whose rows a later step moved elsewhere.
- The result is `release/open-version-schema-census-list.md` (one file per module when over 4 KB): unused, replaced, and a count of what is in use. What a released version brought is left out.
- The architect reads the list and marks each unused or replaced thing removed or kept, with the reason for a kept one.
- SC2, the removals: one PR per module (Workspace, commerce, ERP, the rest). Each takes the adding lines out, adds the clean-up sub-step, corrects `get_tables()` and the memory files, names the `[SCHEME]` item's final state in its body, and is run twice on a dev database that ran the old step and once from the released schema.

## What holds from now on?
- The PR that gives a structure up removes it in the same PR; no later census is needed for it.

## How does the plan close?
- The census file and this plan are deleted once SC2's PRs have landed; the rule stays in `release/released-vs-open-version.md`.
