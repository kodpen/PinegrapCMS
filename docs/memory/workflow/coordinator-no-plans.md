---
name: coordinator-no-plans
description: What a coordinator gives its workers when no plan step is free: a new design template; since 2026-11-06 no bug hunt and no bug issue as work
---

## When does this apply?
- A worker slot is free and no plan step in `plans/` is free (every step done or held by an open PR, or waiting on a decision). Plans still come first whenever one has a free step.
- Set 2026-11-01 so that workers never sit idle when the plans run out.

## Are bug issues or a bug hunt given out?
- No, since 2026-11-06. Workers only build the project: a free plan step, else a new design template. No coordinator starts a bug hunt or gives a `bug` issue to a worker, and no worker claims one.
- The open `bug` issues (the test coordinator's and those of the earlier hunts) wait for the owner (`test-coordinator.md`).
- Why: the workers' quota goes to building the project. On 2026-11-06 seven new hunts started within five minutes, beside the fixes of the earlier rounds.
- The hunt and fix PRs already open then were finished by their own sessions and landed as usual; the hunt's plan files (`plans/bug-hunt*.md`) were then deleted.

## When is a new design template started?
- When a slot is still free and no template plan is open.
- The coordinator picks the next candidate the product does not ship yet (shipped: `includes/design_templates/`), in this order (owner's list, 2026-11-01): web designer portfolio, mobile app developer, social media (Instagram-like), modern phone launch site, car dealership, hotel, marketplace; then real estate agency, law or consulting firm, online courses, events and conferences, beauty salon with booking.
- 2026-11-06: every candidate of that list is shipped (`includes/design_templates/`) or in an open plan; a coordinator then picks the next sector itself. Design templates are second (owner, 2026-11-07, `plans/feature-plans-2026-11.md`): a free slot takes a free step of an opened plan first; a template step or a new template plan only when none is free.
- It starts a worker with it. That worker writes `plans/design-template-<name>.md` cut into ~1,000-line steps, following the forum and magazine templates (`designer/design-template-*.md`), and claims step 1.
- General features only: a template that needs a new schema, module or product behaviour asks the architect first (likely for social media follows and likes, and for marketplace sellers).

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/test-coordinator.md, docs/memory/designer/README.md
