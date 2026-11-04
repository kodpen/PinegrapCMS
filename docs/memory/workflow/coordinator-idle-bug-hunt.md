---
name: coordinator-idle-bug-hunt
description: While the merge line keeps half of a coordinator's workers waiting, it starts 2 to 3 extra bug-hunt workers outside its worker count; once the line is clear, each is archived when it finishes
---

## Why?
- While the trains work through the queue, many workers sit with a `ready` PR or wait for a merge before their next step, and the account's quota window runs on unused. Extra bug hunters spend it on faults instead.

## When does a coordinator start them?
- At a check-in where at least half of its running workers wait on the merge line: their PR is `ready` and not merged yet, or their plan's next step waits for that merge (CLAUDE.md, Working mode 1).
- It then keeps 2 to 3 extra bug-hunt workers running, started one a minute. The bug hunt runs as in `coordinator-no-plans.md`: one module per worker, tracked in `plans/bug-hunt.md`, a failing test first, `[iş] Bug hunt: <module> — <fault>` PRs.
- It starts each with `create_session` and the tag `bug-hunt-extra`, and marks it "extra hunt" in its to-do list and handover note, so it is never mistaken for a regular worker.

## What do they count against?
- Not against the coordinator's worker count (4 to 10, `coordinator-quota.md`), nor against its 6 places in the queue (CLAUDE.md, Working mode 8).
- The queue limit of 20 open PRs still holds: over it, no extra hunter starts. The quota guard of `coordinator-limits.md` still holds: a refused status (`rejected`) starts none.

## When are they archived?
- While the line still keeps half of the workers waiting, a hunter that finished its module (its PR merged, or the module marked "clean") may take the next free module.
- Once the waiting PRs have landed and no `ready` PR is left in the queue, each extra hunter is archived (`archive_session`) as soon as it finishes: its PR merged, or its module marked "clean" with no PR open. It is not replaced; free work goes to the regular workers again.
- A hunter with a PR still open is not archived: it waits for its merge like any worker.

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/coordinator-no-plans.md, docs/memory/workflow/coordinator-quota.md
