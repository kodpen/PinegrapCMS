---
name: memory-rule-checks
description: Plan: the tool refuses a new memory line over 500 characters and a cluster of ten files with no start file; the nine missing cluster overviews; steps MR1-MR5
---

## What is it for?
- Two rules of `workflow/memory-writing.md` are read by no tool and fall behind. Measured on 2026-11-11 (development at #8712): 1,299 lines over 500 characters in 910 hand-written files; of 24 clusters of ten files or more, nine have no start file.
- The search tool shows a long line as "[Omitted long matching line]", so its words are never found; a cluster with no start file is read file by file (the trials in `memory-writing.md`).
- Architect (2026-11-11): the old long lines are not swept in one go. 910 files edited under the open PRs of about fifteen sessions cost more in conflicts than the lines cost in searches. The tool stops new ones; a session that edits a file splits that file's other long lines in the same PR.

## Which steps are there?
- All S. MR1 and MR5 edit a tool and its test; MR2-MR4 are memory only (no draft claim; the open-PR check holds).
- MR1 The tool refuses a new long line: `tools/memory_index.php`, the run and `--check`.
  - A line over 500 characters (characters, not bytes) that the branch adds or rewrites in a file under `docs/memory/` is an error naming the file and the line. A line standing word for word in the same file at the point the branch left `origin/development` (as `tools/pre_push.sh` finds it) is let through; a new or renamed file has none.
  - Generated lines are not counted; a README's hand-written key-rules block is.
  - Where the base cannot be read (no git, a shallow CI checkout) the check does not pass in silence: the CI job fetches what the compare needs.
  - An open PR that adds a long line fails once MR1 has landed: its session splits the line.
  - Tests in `tests/memory_index_test.php`, the compare as a pure function: added, rewritten, unchanged, new file, a line of exactly 500. `memory-writing.md` names the check.
- MR2 Workspace: `workspace/workspace-channel-overview.md` (17 files), `workspace-voice-overview.md` (13), `workspace-api-overview.md` (11). The first to go: the documentation pages are written from overviews (`workflow/user-docs-pages.md`).
- MR3 `commerce/carrier-label-overview.md` (13), `ui/settings-modal-overview.md` (12), `data/db-q-overview.md` (28).
- MR4 Designer: `designer/designer-canvas-overview.md` (11), `widget-default-overview.md` (12), `design-template-overview.md` (100; an overview is no template step, the pause of the template plans does not hold it).
- MR5 Close-out: the tool refuses a cluster of ten files or more with no start file.
  - After MR1-MR4. A cluster that reached ten meanwhile gets its overview in this step.
  - A start file named `<prefix>.md` counts, as the tool reads it today (eight clusters have one): no rename, the links stay. `memory-writing.md` says so and names both checks; this file is deleted.

## What does an overview hold?
- What `memory-writing.md` asks: what the feature is, the decisions that bind it (pointing to their files, no value repeated), its parts by the label a person sees, which file holds what, how a new part is added. At most 4 KB; model `ui/image-editor-overview.md`.
- Written from the cluster's files; where a file and the code disagree the file is corrected in the same PR. No product code.
- `php tools/memory_index.php` after adding the file: the folder's README then names it as the cluster's start.

## What is not in it?
- A list of what changed since a documentation mark (`workflow/user-docs-pages.md`): asked when the pages are written.
- Clusters other plans close: `workspace/mail-*` (WM9), `ui/picker-*` (PK10), the Workspace settings (`settings-workspace-move-steps.md`).

## Who holds it, where does it start?
- Coordinators hand the steps out when a worker is free; claim as `[plan:memory-rule-checks] <step> — <topic>`. MR1-MR4 do not wait for each other (no two share a folder); MR5 is the last and deletes this file.
