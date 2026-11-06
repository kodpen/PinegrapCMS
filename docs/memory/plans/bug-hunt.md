---
name: bug-hunt
description: Plan: module-by-module bug hunt when no plan step is free; how a module is taken, hunted and closed; the module lists are in bug-hunt-modules-*.md
---

## What is this plan?
- A coordinator gives a free worker one module to hunt for real faults (`workflow/coordinator-no-plans.md`). One worker per module, never two on the same files.
- A module is an `includes/fn/` module (`bug-hunt-modules-fn-1.md`, `-fn-1a.md`, `-fn-1a2.md`, `-fn-1b.md`, `-fn-2.md`, `-fn-2b.md`, `-fn-2c.md`, `-fn-2d.md`), a group of root endpoints (`bug-hunt-modules-endpoints-1.md`, `-endpoints-1c.md`, `-endpoints-2.md`, `-endpoints-2b.md`), or a migration file (`bug-hunt-modules-migrations.md`).

## How are root endpoints grouped?
- By area word: the file name without `.php`, without a leading `add_`, `edit_`, `delete_`, `view_`, `update_`, `import_`, `export_`, `duplicate_`, `get_`, `set_`, `save_`, `print_`, `manage_` or `bulk_`, cut at the next `_`, a final `s` dropped (not in `sms`). `edit_user.php` and `view_users.php` are both `user`.
- An area word with one file only sits on a line of up to six named endpoints.
- A new endpoint joins its area's line; a big group (`erp`, `workspace`, `order`) may be split by the worker who takes it, naming the files on each line.

## How is a module taken?
- Check the open PRs first (CLAUDE.md, claim step 1): a PR changing the module's line holds it.
- The hunter's one draft PR (docs only, `[iş] Bug hunt: <modules>`) changes the module's line from `open` to `taken`; the line names the files when the group is not obvious.

## How is a module hunted?
- Read `workflow/decisions-made.md` and its parts first: a listed place is not a finding.
- Read the code, run its tests, look for wrong results, unescaped output, missing CSRF or permission checks, SQL traps (`data/`), PHP 7.1–8.5 breakage.
- One `bug` issue per fault (`workflow/test-coordinator.md`): the hunter fixes nothing; a worker takes the issue as free work and opens the fix PR, failing test first.
- A fault needing a product or schema decision goes to an `architect` issue; a large one becomes a plan.

## How is a module closed?
- Its line says `done` with the issue numbers, or `clean` when nothing was found, in the hunter's draft PR, which then goes ready (docs only, it lands alone).
- When every line is done or clean, the lasting findings move to their topic folders and these files are deleted.
