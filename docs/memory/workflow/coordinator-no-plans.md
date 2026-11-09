---
name: coordinator-no-plans
description: Design template plans are paused (2026-11-09): no template step and no new template is given out; low priority before that; no bug hunt and no bug issue as work
---

## Are the design template plans paused?
- Yes, for now (owner, 2026-11-09): no step of a `plans/design-template-*` plan (new templates and the polishing round) is given out, and no new design template is started. A free slot with no step of the main list stays empty.
- `tools/free_work.php` prints the low-priority list as "paused" while `FREE_WORK_LOW_PRIORITY_PAUSED` is true; setting it back to false lists those steps again under the rules below.
- A template PR already open is finished by its own session and lands as usual; it takes no next template step.
- Why: coordinators still took template steps first while feature plan steps waited.

## When does this apply?
- A worker slot is free and no plan step in `plans/` is free (every step done or held by an open PR, or waiting on a decision). Plans still come first whenever one has a free step.
- Set 2026-11-01 so that workers never sit idle when the plans run out.

## Are design template plans low priority?
- Yes (owner, 2026-11-08): every `plans/design-template-*` plan, new templates and the polishing round alike, and any plan whose description says "low priority".
- `tools/free_work.php` lists their free steps apart, under "Low priority", after the others. A free slot takes one only when no step of the main list can be given (each held, waiting on its "After", or on a decision).
- A worker whose template step landed takes the plan's next step on the same terms (CLAUDE.md, Working mode 6); else it ends and its slot goes to the main list.
- Why: coordinators gave template steps out while feature plan steps waited.

## Are bug issues or a bug hunt given out?
- No, since 2026-11-06. Workers only build the project: a free plan step, else a new design template. No coordinator starts a bug hunt or gives a `bug` issue to a worker, and no worker claims one.
- The open `bug` issues (the test coordinator's and those of the earlier hunts) wait for the owner (`test-coordinator.md`).
- Why: the workers' quota goes to building the project (on 2026-11-06 seven new hunts started within five minutes).

## When is a new design template started?
- When a slot is still free and no template plan is open.
- The coordinator picks the next candidate the product does not ship yet (shipped: `includes/design_templates/`), in this order (owner's list, 2026-11-01): web designer portfolio, mobile app developer, social media (Instagram-like), modern phone launch site, car dealership, hotel, marketplace; then real estate agency, law or consulting firm, online courses, events and conferences, beauty salon with booking.
- 2026-11-06: every candidate of that list is shipped (`includes/design_templates/`) or in an open plan; a coordinator then picks the next sector itself, only when no step of either `free_work.php` list is free.
- It starts a worker with it. That worker writes `plans/design-template-<name>.md` cut into ~1,000-line steps and claims step 1. Since 2026-11-07 step 1 is the template's palette block family, then the pages, and the plan says what makes the template unlike the shipped ones (`designer/design-template-own-look.md`). "Built like X" in a plan names the file layout only, never the look.
- The polishing round of the shipped templates (`plans/design-template-own-looks.md`) is low priority too; its free steps come before a new template plan.
- General features only: a template that needs a new schema, module or product behaviour asks the architect first (likely for social media follows and likes, and for marketplace sellers).

Related: docs/memory/designer/design-template-own-look.md, docs/memory/workflow/coordinator.md, docs/memory/workflow/test-coordinator.md, docs/memory/designer/README.md
