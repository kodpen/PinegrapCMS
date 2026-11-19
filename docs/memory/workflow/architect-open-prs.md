---
name: architect-open-prs
description: The architect's docs-only PRs are not held to one open PR at a time: a plan or a record does not wait for an earlier one to land (2026-11-19)
---

## May the architect hold more than one open PR?
- Yes, for its docs-only PRs (plans, decision records, working rules). Each branches from `development`, never from another of its branches, and touches no file an open one of its own touches.
- `CLAUDE.md` Working mode 1 (one open PR per session) stays for every session that writes product code.

## Why?
- Architect (2026-11-19): during the week's pause its PR #10352 waited labelled `ready` with the merging session closed, and it held a plan the owner had just asked for back until the next week. The owner, told so: "kuralı boşver sen mimarsın."
- A docs-only PR lands alone, outside the trains; a plan that waits a week costs a coordinator its next work.

Related: docs/memory/workflow/architect.md, docs/memory/workflow/working-mode.md
