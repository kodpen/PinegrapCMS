---
name: coordinator-no-plans
description: What a coordinator gives its workers when no plan step is free: open issues first, then a module-by-module bug hunt, then a new design template
---

## When does this apply?
- A worker slot is free and no plan step in `plans/` is free (every step done or held by an open PR, or waiting on a decision). Plans still come first whenever one has a free step.
- Set 2026-11-01 so that workers never sit idle when the plans run out.

## What comes first?
- Open issues. Every open issue that is not waiting on the architect (`architect` label with no decision yet) goes to a worker, `bug` issues first, oldest first:
  `php tools/free_work.php` lists them: the open issues no open PR names, the `architect` ones apart.
- The worker claims it as `test-coordinator.md` describes (draft `[iş] <topic>` with `Fixes #<n>`). An issue that no longer reproduces or is already done: the worker says so on the issue with the commit or PR, and the merging session closes it.

## How does the bug hunt run?
- One worker per module: an `includes/fn/` module, an endpoint or one panel screen, never two workers on the same files.
- The hunt is tracked in `plans/bug-hunt.md`: the first hunt worker creates it with the module list (`core/fn-modules.md`, the endpoints at the root); each worker marks its module with its issue numbers or "clean".
- The worker reads `workflow/decisions-made.md` first, reads the module's code, runs its tests and looks for real faults: wrong results, unescaped output, missing CSRF or permission checks, SQL traps (`data/`), PHP 7.1–8.5 breakage.
- It opens one `bug` issue per fault, written as `test-coordinator.md` asks (where, the code path or steps, expected, seen), and fixes nothing itself: the issues go to workers as free work (open issues come first, above). Its one PR is the docs-only draft that takes its modules in the plan file and closes them with the issue numbers or "clean" (`plans/bug-hunt.md`).
- A finding that needs a product or schema decision goes to an `architect` issue; a large one becomes a plan.

## Why issues and not a PR per fault?
- On 2026-11-05 a hunt opened a PR per fault, over a hundred, and sent them to CI together: the CI queue and the merge line were blocked for about 12 hours. An issue costs no CI run and no queue place; a worker takes it when a slot is free. No extra hunters run beside the workers any more.

## When is a new design template started?
- When a slot is still free after the issues and the open bug-hunt modules, and no template plan is open.
- The coordinator picks the next candidate the product does not ship yet (shipped: `includes/design_templates/`), in this order (owner's list, 2026-11-01): web designer portfolio, mobile app developer, social media (Instagram-like), modern phone launch site, car dealership, hotel, marketplace; then real estate agency, law or consulting firm, online courses, events and conferences, beauty salon with booking.
- It starts a worker with it. That worker writes `plans/design-template-<name>.md` cut into ~1,000-line steps, following the forum and magazine templates (`designer/design-template-*.md`), and claims step 1.
- General features only: a template that needs a new schema, module or product behaviour asks the architect first (likely for social media follows and likes, and for marketplace sellers).

Related: docs/memory/workflow/coordinator.md, docs/memory/workflow/test-coordinator.md, docs/memory/designer/README.md
