---
name: feature-plans-2026-11
description: The eleven feature plans approved 2026-11-02: index and order, how one coordinator and its five workers hold a plan, the shared rules
---

## What was decided? (owner, 2026-11-02)
- Eleven features from the November gap review become plans, one file pair each (the plan, its steps). The twelfth candidate (design variables and classes) was left out, and stays out: with Bootstrap on the site output it would not pay (owner, 2026-11-06).
- Every plan is held by **one coordinator and its five workers**; two coordinators run, so two plans are in work at a time. A coordinator finishes its plan before taking the next one from the order below.
- Pinegrap's audience is mixed (bloggers, shops, agencies, corporate sites) and global: nothing in these plans is built around one country, and products or invoices are never put first.

## Which plans, in which order?
- The eleven landed; the last one (store credit) in 2026.11.0.
- Opened 2026-11-06 (owner): `designer-performance-and-publish` (the first coordinator; one worker at a time on D1-D5), then `agent-channel` and `api-forms-for-agents` (the second coordinator; the agent channel first, the API plan's free steps beside it).

## How does a coordinator hold a plan?
- It claims nothing itself and writes no code (`workflow/coordinator.md`). It gives its workers only step lines that say `free` or whose "After" steps are merged, at most five at once and never two on the same files; the steps file says which run side by side.
- A worker claims with a draft PR `[plan:<plan file name>] <step> — <topic>` (CLAUDE.md, Claim work), builds the step as its line says, marks the line done with its PR number, and comments "Ready for review" on the draft. The coordinator checks the PR against the step line (files, tests, memory, the `tr_add.php` texts, the bundle rebuild) and answers on the PR; after its "Reviewed" the worker marks the PR ready and puts the `ready` label on when CI is green (Working mode 2).
- A question the plan leaves open is opened by the coordinator as an `architect` issue before the first step that needs it (Working mode 10); steps that need no answer start first. A worker never decides it.
- A step is about 1,000 product lines at most (S under 400, M about 1,000, L cut into PRs); each step writes its memory topic file and its tests; a schema change is one sub-step of the open version's migration, rerun twice on a dev DB.
- When the last step lands, the coordinator's last worker moves the lasting decisions to the topic folders, deletes the plan's files and this file's line for it, and runs `php tools/memory_index.php`.

## Where does it start?
- Each plan's "Where does it start?" names its first steps; the coordinator reads the plan, its steps file and the memory folders they name before handing anything out.
